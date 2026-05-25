<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\HrisEmployee;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{

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

    /**
     * Cleanup expired trusted devices.
     */
    public function cleanupExpiredDevices(): void
    {
        TrustedDevice::where('trusted_until', '<', now())->delete();
    }
}
