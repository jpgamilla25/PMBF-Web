<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterLookupRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EmployeeSnapshotService;
use App\Services\HrisService;
use App\Services\OtpService;
use App\Services\QrLoginService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService,
        private readonly HrisService $hrisService,
        private readonly OtpService $otpService,
        private readonly QrLoginService $qrLoginService,
    ) {}

    // ─── Registration ─────────────────────────────────────────

    public function lookup(RegisterLookupRequest $request): JsonResponse
    {
        $employeeId = $request->validated('employee_id');

        if (User::where('employee_id', $employeeId)->exists()) {
            return $this->error('This employee ID is already registered.', 409);
        }

        $result = $this->hrisService->validateEmployee($employeeId);
        if (!$result['valid']) {
            // An HRIS outage is a 503 the client can retry, not a 404 that
            // tells the user their employee ID doesn't exist.
            return $this->error($result['message'], $result['available'] ? 404 : 503);
        }

        $employee = $result['employee'];
        $this->otpService->generate($employee->email, 'registration');

        return $this->success([
            'employee_id' => $employee->employee_id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'employment_type' => $employee->employment_type,
            'department' => $employee->department,
            'position' => $employee->position,
            'email' => $this->maskEmail($employee->email),
        ], 'OTP sent to your registered email address.');
    }

    public function completeRegistration(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'otp' => 'required|string|size:6',
            'device_fingerprint' => 'nullable|string|max:128',
            'trust_device' => 'nullable|boolean',
        ]);

        $result = $this->hrisService->validateEmployee($request->employee_id);
        if (!$result['valid']) {
            return $this->error($result['message'], $result['available'] ? 404 : 503);
        }

        if (User::where('employee_id', $request->employee_id)->exists()) {
            return $this->error('This employee ID is already registered.', 409);
        }

        $employee = $result['employee'];
        if (!$this->otpService->verify($employee->email, $request->otp, 'registration')) {
            return $this->error('Invalid or expired OTP.', 422);
        }

        $user = $this->authService->register($employee);
        $loginResult = $this->authService->loginByEmployeeId(
            $user->employee_id,
            $request->device_fingerprint
        );

        // Auto-trust device on registration
        if ($request->device_fingerprint) {
            $this->authService->trustDevice(
                $user,
                $request->device_fingerprint,
                $request->header('User-Agent'),
                $request->ip()
            );
        }

        return $this->success([
            'user' => new UserResource($loginResult['user']),
            'token' => $loginResult['token'],
        ], 'Registration completed successfully.', 201);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'type' => 'required|in:registration,login,loan_application',
        ]);

        $this->otpService->generate($request->email, $request->type);

        return $this->success(null, 'OTP has been resent.');
    }

    // ─── Login Step 1: Request OTP or check trusted device ────

    public function loginRequestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'device_fingerprint' => 'nullable|string|max:128',
        ]);

        $user = User::where('employee_id', $request->employee_id)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return $this->error('Employee ID not found or account is not active.', 404);
        }

        // ── Check if device is trusted → skip OTP ──
        if ($request->device_fingerprint &&
            $this->authService->isDeviceTrusted($request->employee_id, $request->device_fingerprint)
        ) {
            // A PIN holder is challenged for it instead of being let straight
            // in — a trusted browser alone no longer grants access.
            if ($user->pin && !$this->authService->isPinLocked($user)) {
                return $this->success([
                    'trusted' => true,
                    'requires_pin' => true,
                    'employee_id' => $user->employee_id,
                    'first_name' => $user->first_name,
                ], 'Enter your PIN to continue.');
            }

            $loginResult = $this->authService->loginByEmployeeId(
                $request->employee_id,
                $request->device_fingerprint
            );

            $this->authService->touchTrustedDevice($user, $request->device_fingerprint);

            return $this->success([
                'trusted' => true,
                'requires_pin' => false,
                'user' => new UserResource($loginResult['user']),
                'token' => $loginResult['token'],
            ], 'Trusted device — logged in automatically.');
        }

        // ── Not trusted → send OTP ──
        $this->otpService->generate($user->email, 'login');

        return $this->success([
            'trusted' => false,
            'employee_id' => $user->employee_id,
            'first_name' => $user->first_name,
            'email' => $this->maskEmail($user->email),
        ], 'OTP sent to your email.');
    }

    // ─── Login Step 2: Verify OTP + optional trust ────────────

    public function loginVerifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'otp' => 'required|string|size:6',
            'device_fingerprint' => 'nullable|string|max:128',
            'trust_device' => 'nullable|boolean',
        ]);

        $user = User::where('employee_id', $request->employee_id)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return $this->error('Employee ID not found.', 404);
        }

        if (!$this->otpService->verify($user->email, $request->otp, 'login')) {
            return $this->error('Invalid or expired OTP.', 422);
        }

        $loginResult = $this->authService->loginByEmployeeId(
            $user->employee_id,
            $request->device_fingerprint
        );

        // ── Trust device if requested ──
        $trustedUntil = null;
        if ($request->boolean('trust_device') && $request->device_fingerprint) {
            $trusted = $this->authService->trustDevice(
                $user,
                $request->device_fingerprint,
                $request->header('User-Agent'),
                $request->ip()
            );
            $trustedUntil = $trusted->trusted_until->toIso8601String();
        }

        return $this->success([
            'user' => new UserResource($loginResult['user']),
            'token' => $loginResult['token'],
            'trusted_until' => $trustedUntil,
        ], 'Login successful.');
    }

    // ─── PIN Login ────────────────────────────────────────────

    /**
     * Tell the client which login screen to show for this ID + device,
     * without revealing whether the account exists.
     */
    public function pinStatus(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'device_fingerprint' => 'required|string|max:128',
        ]);

        $user = User::where('employee_id', $request->employee_id)
            ->where('status', 'active')
            ->first();

        $available = $user
            && $user->pin
            && $this->authService->isDeviceTrusted($request->employee_id, $request->device_fingerprint);

        if (!$available) {
            return $this->success(['pin_available' => false]);
        }

        return $this->success([
            'pin_available' => true,
            'first_name' => $user->first_name,
            'email' => $this->maskEmail($user->email),
            'locked' => $this->authService->isPinLocked($user),
            'locked_until' => $user->pin_locked_until?->toIso8601String(),
        ]);
    }

    /**
     * Log in with a 4-digit PIN on an already-trusted device.
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'pin' => 'required|digits:4',
            'device_fingerprint' => 'required|string|max:128',
        ]);

        $result = $this->authService->verifyPin(
            $request->employee_id,
            $request->pin,
            $request->device_fingerprint
        );

        if (!$result['ok']) {
            return match ($result['reason']) {
                'locked' => $this->error(
                    'Too many incorrect PINs. PIN login is locked — sign in with an OTP instead.',
                    423,
                    ['locked_until' => $result['locked_until'] ?? null]
                ),
                'incorrect' => $this->error(
                    'Incorrect PIN. ' . $result['attempts_left'] . ' attempt(s) remaining.',
                    422,
                    ['attempts_left' => $result['attempts_left']]
                ),
                default => $this->error('PIN login is not available on this device.', 403),
            };
        }

        $loginResult = $this->authService->loginByEmployeeId(
            $result['user']->employee_id,
            $request->device_fingerprint
        );

        return $this->success([
            'user' => new UserResource($loginResult['user']),
            'token' => $loginResult['token'],
        ], 'Login successful.');
    }

    /**
     * Create or change the PIN for the signed-in user.
     * Changing an existing PIN requires the current one.
     */
    public function setPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|digits:4|confirmed',
            'current_pin' => 'nullable|digits:4',
            'device_fingerprint' => 'required|string|max:128',
        ]);

        $user = $request->user();

        if ($user->pin) {
            if (!$request->filled('current_pin')) {
                return $this->error('Enter your current PIN to change it.', 422);
            }

            if (!$this->authService->checkPin($user, $request->current_pin)) {
                return $this->error('Your current PIN is incorrect.', 422);
            }
        }

        if ($this->authService->isWeakPin($request->pin)) {
            return $this->error('That PIN is too easy to guess. Avoid repeated or sequential digits.', 422);
        }

        $this->authService->setPin(
            $user,
            $request->pin,
            $request->device_fingerprint,
            $request->header('User-Agent'),
            $request->ip()
        );

        return $this->success(['has_pin' => true], 'PIN saved. Use it to sign in on this device.');
    }

    /**
     * Remove the PIN — the user falls back to Employee ID → OTP.
     */
    public function removePin(Request $request): JsonResponse
    {
        $this->authService->clearPin($request->user());

        return $this->success(['has_pin' => false], 'PIN removed.');
    }

    /**
     * Forgot PIN, step 1: email an OTP.
     */
    public function pinResetRequest(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|string']);

        $user = User::where('employee_id', $request->employee_id)
            ->where('status', 'active')
            ->first();

        // Always answer the same way so this can't confirm an account exists.
        if ($user) {
            $this->otpService->generate($user->email, 'pin_reset');
        }

        return $this->success([
            'email' => $user ? $this->maskEmail($user->email) : null,
        ], 'If the account exists, a reset code has been sent to its registered email.');
    }

    /**
     * Forgot PIN, step 2: verify the OTP and set a new PIN.
     * Succeeding here also signs the user in and trusts the device,
     * so a locked-out user can recover in one pass.
     */
    public function pinResetConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|string',
            'otp' => 'required|string|size:6',
            'pin' => 'required|digits:4|confirmed',
            'device_fingerprint' => 'required|string|max:128',
        ]);

        $user = User::where('employee_id', $request->employee_id)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return $this->error('Employee ID not found.', 404);
        }

        if (!$this->otpService->verify($user->email, $request->otp, 'pin_reset')) {
            return $this->error('Invalid or expired reset code.', 422);
        }

        if ($this->authService->isWeakPin($request->pin)) {
            return $this->error('That PIN is too easy to guess. Avoid repeated or sequential digits.', 422);
        }

        $this->authService->setPin(
            $user,
            $request->pin,
            $request->device_fingerprint,
            $request->header('User-Agent'),
            $request->ip()
        );

        $loginResult = $this->authService->loginByEmployeeId(
            $user->employee_id,
            $request->device_fingerprint
        );

        return $this->success([
            'user' => new UserResource($loginResult['user']),
            'token' => $loginResult['token'],
        ], 'New PIN set. You are signed in.');
    }

    // ─── QR Login ─────────────────────────────────────────────

    public function qrGenerate(): JsonResponse
    {
        $session = $this->qrLoginService->createSession();

        return $this->success([
            'session_token' => $session->session_token,
            'expires_at' => $session->expires_at->toIso8601String(),
            'qr_data' => json_encode([
                'action' => 'pmbf_qr_login',
                'token' => $session->session_token,
            ]),
        ], 'QR session created.');
    }

    public function qrStatus(string $sessionToken): JsonResponse
    {
        $session = $this->qrLoginService->checkStatus($sessionToken);

        if (!$session) {
            return $this->error('Session not found.', 404);
        }

        if ($session->status === 'approved' && $session->user_id) {
            $result = $this->authService->loginByEmployeeId(
                $session->user->employee_id
            );

            return $this->success([
                'status' => 'approved',
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'QR login approved.');
        }

        return $this->success(['status' => $session->status]);
    }

    public function qrApprove(Request $request): JsonResponse
    {
        $request->validate(['session_token' => 'required|string']);

        $approved = $this->qrLoginService->approveSession(
            $request->session_token,
            $request->user()
        );

        if (!$approved) {
            return $this->error('Session expired or invalid.', 422);
        }

        return $this->success(null, 'Login approved from mobile.');
    }

    // ─── Session & Device Management ──────────────────────────

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            // Single record, so a live HRIS lookup is affordable here — this
            // is what keeps the signed-in user's pay figures current.
            (new UserResource($request->user()))->withHris(),
            'User retrieved.'
        );
    }

    /**
     * Pull this member's employment details from HRIS on demand and write them
     * back to their local record.
     *
     * Loan decisions already read HRIS live, so this is not what makes an
     * application correct — it refreshes the copy that list views and reports
     * read, which otherwise only updates on the nightly sync.
     */
    public function syncFromHris(Request $request, EmployeeSnapshotService $snapshots): JsonResponse
    {
        $user = $request->user();
        $result = $snapshots->refresh($user);

        if (!$result['available']) {
            return $this->error(
                'HRIS is not reachable right now. Your details are unchanged — please try again shortly.',
                503
            );
        }

        $user->refresh();

        return $this->success([
            'user' => (new UserResource($user))->withHris(),
            'changed' => $result['changed'],
            'changed_fields' => array_keys($result['changes']),
            'synced_at' => $user->hris_synced_at?->toIso8601String(),
        ], $result['changed']
            ? 'Your details have been updated from HRIS.'
            : 'Your details are already up to date.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully.');
    }

    /**
     * Logout everywhere — revoke all web tokens + optionally untrust device.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->where('name', 'web-token')->delete();

        return $this->success(null, 'All web sessions have been terminated.');
    }

    /**
     * List trusted devices for current user.
     */
    public function trustedDevices(Request $request): JsonResponse
    {
        $devices = $this->authService->getTrustedDevices($request->user());

        return $this->success($devices->map(fn ($d) => [
            'id' => $d->id,
            'device_name' => $d->device_name,
            'device_fingerprint' => $d->device_fingerprint,
            'ip_address' => $d->ip_address,
            'trusted_until' => $d->trusted_until->toIso8601String(),
            'last_used_at' => $d->last_used_at?->toIso8601String(),
        ]));
    }

    /**
     * Revoke trust for a specific device.
     */
    public function revokeTrust(Request $request): JsonResponse
    {
        $request->validate(['device_fingerprint' => 'required|string']);

        $this->authService->revokeTrust(
            $request->user(),
            $request->device_fingerprint
        );

        return $this->success(null, 'Device trust revoked.');
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email);
        return substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 0)) . '@' . $domain;
    }
}
