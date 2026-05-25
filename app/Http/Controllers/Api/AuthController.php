<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterLookupRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
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
            return $this->error($result['message'], 404);
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
            return $this->error($result['message'], 404);
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
            $loginResult = $this->authService->loginByEmployeeId(
                $request->employee_id,
                $request->device_fingerprint
            );

            $this->authService->touchTrustedDevice($user, $request->device_fingerprint);

            return $this->success([
                'trusted' => true,
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
            new UserResource($request->user()),
            'User retrieved.'
        );
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
            'ip_address' => $d->ip_address,
            'trusted_until' => $d->trusted_until->toIso8601String(),
            'last_used_at' => $d->last_used_at?->toIso8601String(),
            'is_current' => false, // frontend can compare fingerprints
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
