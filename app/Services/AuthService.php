<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\HrisEmployee;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** Wrong PINs allowed before the account is locked out of PIN login. */
    public const PIN_MAX_ATTEMPTS = 5;

    /** How long a PIN lockout lasts, in minutes. */
    public const PIN_LOCKOUT_MINUTES = 15;

    /**
     * Register a new user from HRIS data (passwordless).
     */
    public function register(HrisEmployee $employee): User
    {
        return User::create([
            'employee_id' => $employee->employee_id,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'last_name' => $employee->last_name,
            'suffix' => $employee->suffix,
            'email' => $employee->email,
            'mobile' => $employee->mobile,
            'employment_type' => $employee->employment_type,
            'position' => $employee->position,
            'department' => $employee->department,
            'base_pay' => $employee->base_pay,
            'take_home_pay' => $employee->take_home_pay,
            'contract_start' => $employee->contract_start,
            'contract_end' => $employee->contract_end,
            'password' => bcrypt(Str::random(32)),
            'role' => 'member',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Issue a web token after OTP verification.
     * Enforces single-session: revokes all previous web tokens.
     *
     * @return array{user: User, token: string}
     */
    public function loginByEmployeeId(string $employeeId, ?string $deviceFingerprint = null): array
    {
        $user = User::where('employee_id', $employeeId)->firstOrFail();

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'employee_id' => ['Your account is not active. Please contact the administrator.'],
            ]);
        }

        // ── Single session enforcement ──
        // Revoke ALL previous web tokens for this user
        $user->tokens()->where('name', 'web-token')->delete();

        // Create new token with device fingerprint
        $token = $user->createToken('web-token', ['*']);

        // Store fingerprint on the token record
        if ($deviceFingerprint) {
            $token->accessToken->forceFill([
                'device_fingerprint' => $deviceFingerprint,
            ])->save();
        }

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Check if a device is trusted for a given user.
     */
    public function isDeviceTrusted(string $employeeId, string $deviceFingerprint): bool
    {
        $user = User::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return false;
        }

        return TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->where('trusted_until', '>', now())
            ->exists();
    }

    /**
     * Trust the current device for 30 days.
     */
    public function trustDevice(User $user, string $deviceFingerprint, ?string $deviceName = null, ?string $ip = null): TrustedDevice
    {
        return TrustedDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $deviceFingerprint,
            ],
            [
                'device_name' => $deviceName,
                'ip_address' => $ip,
                'trusted_until' => now()->addDays((int) Configuration::getValue('mobile_trust_device_days', 30)),
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Refresh last_used_at on a trusted device.
     */
    public function touchTrustedDevice(User $user, string $deviceFingerprint): void
    {
        TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->update(['last_used_at' => now()]);
    }

    /**
     * Revoke trust for a device.
     */
    public function revokeTrust(User $user, string $deviceFingerprint): void
    {
        TrustedDevice::where('user_id', $user->id)
            ->where('device_fingerprint', $deviceFingerprint)
            ->delete();
    }

    /**
     * Get all trusted devices for a user.
     */
    public function getTrustedDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return TrustedDevice::where('user_id', $user->id)
            ->where('trusted_until', '>', now())
            ->orderByDesc('last_used_at')
            ->get();
    }

    /**
     * Logout: revoke current token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    // ─── PIN authentication ───────────────────────────────────

    /**
     * Reject PINs that are trivially guessable: all-same digits
     * (0000, 1111) and straight runs (1234, 4321).
     */
    public function isWeakPin(string $pin): bool
    {
        if (preg_match('/^(\d)\1+$/', $pin)) {
            return true;
        }

        $ascending = true;
        $descending = true;
        for ($i = 1; $i < strlen($pin); $i++) {
            $step = (int) $pin[$i] - (int) $pin[$i - 1];
            if ($step !== 1) {
                $ascending = false;
            }
            if ($step !== -1) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }

    /**
     * Hash and store a PIN, then trust the device it was created on —
     * a PIN is only usable from a trusted device, so setting one without
     * trusting would lock the user out of their own PIN.
     */
    public function setPin(User $user, string $pin, ?string $deviceFingerprint = null, ?string $deviceName = null, ?string $ip = null): void
    {
        $user->forceFill([
            'pin' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();

        if ($deviceFingerprint) {
            $this->trustDevice($user, $deviceFingerprint, $deviceName, $ip);
        }
    }

    /**
     * Remove a user's PIN. They fall back to the Employee ID → OTP flow.
     */
    public function clearPin(User $user): void
    {
        $user->forceFill([
            'pin' => null,
            'pin_set_at' => null,
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();
    }

    /**
     * Plain PIN check for an already-authenticated user (e.g. changing a PIN).
     * Not device-scoped — the caller already holds a valid session token.
     */
    public function checkPin(User $user, string $pin): bool
    {
        return $user->pin !== null && Hash::check($pin, $user->pin);
    }

    public function isPinLocked(User $user): bool
    {
        return $user->pin_locked_until !== null && $user->pin_locked_until->isFuture();
    }

    /**
     * Verify a PIN for a given device.
     *
     * The PIN is device-scoped: it is only accepted from a device that has
     * already been proven via OTP. A leaked PIN alone is therefore useless
     * from another browser.
     *
     * @return array{ok: bool, reason?: string, attempts_left?: int, locked_until?: string, user?: User}
     */
    public function verifyPin(string $employeeId, string $pin, string $deviceFingerprint): array
    {
        $user = User::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->first();

        // Same generic answer for unknown ID, no PIN set and untrusted device,
        // so this endpoint can't be used to enumerate accounts.
        if (!$user || !$user->pin || !$this->isDeviceTrusted($employeeId, $deviceFingerprint)) {
            return ['ok' => false, 'reason' => 'unavailable'];
        }

        if ($this->isPinLocked($user)) {
            return [
                'ok' => false,
                'reason' => 'locked',
                'locked_until' => $user->pin_locked_until->toIso8601String(),
            ];
        }

        if (!Hash::check($pin, $user->pin)) {
            $attempts = $user->pin_attempts + 1;

            if ($attempts >= self::PIN_MAX_ATTEMPTS) {
                $lockedUntil = now()->addMinutes(self::PIN_LOCKOUT_MINUTES);
                $user->forceFill([
                    'pin_attempts' => 0,
                    'pin_locked_until' => $lockedUntil,
                ])->save();

                return [
                    'ok' => false,
                    'reason' => 'locked',
                    'locked_until' => $lockedUntil->toIso8601String(),
                ];
            }

            $user->forceFill(['pin_attempts' => $attempts])->save();

            return [
                'ok' => false,
                'reason' => 'incorrect',
                'attempts_left' => self::PIN_MAX_ATTEMPTS - $attempts,
            ];
        }

        $user->forceFill(['pin_attempts' => 0, 'pin_locked_until' => null])->save();
        $this->touchTrustedDevice($user, $deviceFingerprint);

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Cleanup expired trusted devices.
     */
    public function cleanupExpiredDevices(): void
    {
        TrustedDevice::where('trusted_until', '<', now())->delete();
    }
}
