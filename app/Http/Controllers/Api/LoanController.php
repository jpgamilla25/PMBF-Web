<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Configuration;
use App\Models\Loan;
use App\Models\User;
use App\Services\AuthService;
use App\Services\LoanNotificationService;
use App\Services\LoanService;
use App\Services\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly LoanService $loanService,
        private readonly OtpService $otpService,
        private readonly LoanNotificationService $notificationService,
        private readonly AuthService $authService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->loans()
            ->with(['coMaker', 'approvals.approver'])
            ->withCount('payments');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('loan_type')) {
            $query->where('loan_type', $request->input('loan_type'));
        }

        $loans = $query->latest()->paginate($request->input('per_page', 15));

        return $this->paginated($loans, LoanResource::class);
    }

    /**
     * Submit loan application.
     * If loan_otp_required is ON → sends OTP, returns preview.
     * If loan_otp_required is OFF → creates loan directly.
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Check eligibility (with FMIS data + exemption checks)
        $eligibility = $this->loanService->checkEligibility(
            $user,
            $validated['loan_type'],
            (float) $validated['amount'],
            isset($validated['term_months']) ? (int) $validated['term_months'] : null
        );

        if (!$eligibility['eligible']) {
            return $this->error($eligibility['message'], 422, [
                'can_request_exemption' => $eligibility['can_request_exemption'],
                'exemption_type' => $eligibility['exemption_type'],
                'details' => $eligibility['details'],
            ]);
        }

        // If OTP is disabled in config → create loan immediately
        if (!$this->loanService->isOtpRequired()) {
            $loan = $this->loanService->create($user, $validated);
            $loan->load(['user', 'coMaker']);

            return $this->success([
                'loan' => new LoanResource($loan),
                'otp_required' => false,
            ], 'Loan application submitted successfully.', 201);
        }

        // OTP enabled → send OTP, return preview
        $this->otpService->generate($user->email, 'loan_application');

        // Must match the rate create() will store once the OTP is verified.
        $rate = $this->loanService->getInterestRate($user, $validated['loan_type'] ?? null);

        return $this->success([
            'otp_required' => true,
            'loan_data' => $validated,
            'interest_rate' => $rate,
            'monthly_amortization' => $this->loanService->calculateAmortization(
                (float) $validated['amount'],
                $rate,
                (int) $validated['term_months']
            ),
        ], 'OTP sent. Please verify to complete your loan application.');
    }

    /**
     * Verify OTP and create the loan.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate($this->confirmationRules($user, [
            'otp' => 'required|string|size:6',
        ]));

        if (!$this->otpService->verify($user->email, $request->input('otp'), 'loan_application')) {
            return $this->error('Invalid or expired OTP.', 422);
        }

        return $this->createFromRequest($request, $user);
    }

    /**
     * Confirm a loan application with the member's sign-in PIN instead of
     * waiting for an emailed OTP.
     *
     * Same device-scoped rules and lockout as PIN login — the PIN only works
     * on a device already proven by OTP, so this is not a weaker path.
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate($this->confirmationRules($user, [
            'pin' => 'required|digits:4',
            'device_fingerprint' => 'required|string|max:128',
        ]));

        $result = $this->authService->verifyPin(
            $user->employee_id,
            $request->input('pin'),
            $request->input('device_fingerprint')
        );

        if (!$result['ok']) {
            return match ($result['reason']) {
                'locked' => $this->error(
                    'Too many incorrect PINs. Use an email OTP to confirm this application.',
                    423,
                    ['locked_until' => $result['locked_until'] ?? null]
                ),
                'incorrect' => $this->error(
                    'Incorrect PIN. ' . $result['attempts_left'] . ' attempt(s) remaining.',
                    422,
                    ['attempts_left' => $result['attempts_left']]
                ),
                default => $this->error('PIN confirmation is not available on this device.', 403),
            };
        }

        return $this->createFromRequest($request, $user);
    }

    /**
     * Validation shared by both confirmation paths.
     *
     * loan_type is checked against the types this member may actually apply
     * for — without it, the confirmation step could create a loan of a type
     * the application form would never have offered.
     */
    private function confirmationRules(User $user, array $extra): array
    {
        return $extra + [
            'loan_type' => ['required', 'string', Rule::in(array_keys($this->loanService->getAvailableLoanTypes($user)))],
            'amount' => 'required|numeric|min:' . Configuration::getDecimal('min_loan_amount', 1000),
            'purpose' => 'required|string',
            'term_months' => 'required|integer|min:1|max:' . Configuration::getValue('max_loan_term_months', 60),
            'co_maker_id' => 'nullable|integer|exists:users,id',
        ];
    }

    private function createFromRequest(Request $request, User $user): JsonResponse
    {
        // Re-check eligibility at the moment of creation, not just when the
        // application was started. store() checks too, but the confirmation
        // endpoints are directly callable and eligibility can change in
        // between (a loan gets approved, an exemption expires, pay changes).
        $eligibility = $this->loanService->checkEligibility(
            $user,
            $request->input('loan_type'),
            (float) $request->input('amount'),
            $request->filled('term_months') ? (int) $request->input('term_months') : null
        );

        if (!$eligibility['eligible']) {
            return $this->error($eligibility['message'], 422, [
                'can_request_exemption' => $eligibility['can_request_exemption'],
                'exemption_type' => $eligibility['exemption_type'],
                'details' => $eligibility['details'],
            ]);
        }

        $loan = $this->loanService->create($user, $request->only([
            'loan_type', 'amount', 'purpose', 'term_months', 'co_maker_id',
        ]));

        $loan->load(['user', 'coMaker']);

        return $this->success(
            new LoanResource($loan),
            'Loan application submitted successfully.',
            201
        );
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $this->otpService->generate($request->user()->email, 'loan_application');

        return $this->success(null, 'OTP has been resent.');
    }

    public function show(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->user_id !== $request->user()->id && !$request->user()->isStaff()) {
            return $this->error('Unauthorized.', 403);
        }

        $loan->load(['user', 'coMaker', 'approvals.approver', 'payments']);

        return $this->success((new LoanResource($loan))->withHris(), 'Loan retrieved.');
    }

    /**
     * Full amortization schedule + progress summary for a loan.
     * GET /loans/{loan}/schedule
     */
    public function schedule(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->user_id !== $request->user()->id && !$request->user()->isStaff()) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(
            $this->loanService->amortizationSchedule($loan),
            'Amortization schedule retrieved.'
        );
    }

    /**
     * Cancel a pending loan application.
     */
    public function cancel(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        if ($loan->user_id !== $user->id && !$user->isAdmin()) {
            return $this->error('Unauthorized.', 403);
        }

        $cancellableStatuses = [
            'co_maker_pending', 'admin_pending', 'pending', 'receiver_approved', 'committee_approved',
        ];
        if (!in_array($loan->status, $cancellableStatuses)) {
            return $this->error('Only pending loans can be cancelled.', 422);
        }

        // Remember whether a co-maker was still awaiting consent before we change status.
        $hadPendingCoMaker = $loan->status === 'co_maker_pending';

        $loan->update([
            'status' => 'cancelled',
            'remarks' => 'Cancelled by ' . ($loan->user_id === $user->id ? 'applicant' : 'admin'),
            // Invalidate any outstanding co-maker consent link.
            'co_maker_token' => null,
        ]);

        // Email all approvers + admin
        $loan->load('user', 'coMaker');
        $cancelledBy = $loan->user_id === $user->id ? 'applicant' : 'admin';
        $recipients = \App\Models\User::whereIn('role', ['receiver', 'loan_committee', 'chairperson', 'admin'])
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();
        // Also email the applicant if admin cancelled
        if ($cancelledBy === 'admin') {
            $recipients[] = $loan->user->email;
        }
        // Notify the co-maker — they were asked to co-sign (or already consented), so they
        // should know the loan was cancelled and any pending request no longer needs action.
        if ($loan->coMaker && ($hadPendingCoMaker || $loan->co_maker_status === 'approved')) {
            $recipients[] = $loan->coMaker->email;
        }
        foreach (array_unique($recipients) as $email) {
            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\LoanCancelledMail($loan, $cancelledBy));
        }

        return $this->success(null, 'Loan application cancelled.');
    }

    /**
     * Pre-check eligibility before filling the form.
     * POST /loans/check-eligibility { loan_type, amount }
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate([
            'loan_type' => 'required|string',
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'nullable|integer|min:1|max:120',
        ]);

        $result = $this->loanService->checkEligibility(
            $request->user(),
            $request->loan_type,
            (float) $request->amount,
            $request->filled('term_months') ? (int) $request->term_months : null
        );

        return $this->success($result, $result['message']);
    }

    public function types(Request $request): JsonResponse
    {
        return $this->success([
            'types'           => $this->loanService->getAvailableLoanTypes($request->user()),
            'otp_required'    => $this->loanService->isOtpRequired(),
            'min_loan_amount' => Configuration::getDecimal('min_loan_amount', 1000),
            'max_term_months' => (int) Configuration::getValue('max_loan_term_months', 60),
        ], 'Available loan types retrieved.');
    }

    public function stats(Request $request): JsonResponse
    {
        return $this->success(
            $this->loanService->getStats($request->user()),
            'Loan statistics retrieved.'
        );
    }

    /**
     * Get loans where authenticated user is co-maker and consent is still pending.
     */
    public function pendingCoMaker(Request $request): JsonResponse
    {
        $loans = Loan::where('co_maker_id', $request->user()->id)
            ->where('status', 'co_maker_pending')
            ->with('user')
            ->latest()
            ->get();

        return $this->success($loans->map(fn (Loan $l) => [
            'id' => $l->id,
            'loan_type' => $l->loan_type,
            'amount' => $l->amount,
            'term_months' => $l->term_months,
            'monthly_amortization' => $l->monthly_amortization,
            'purpose' => $l->purpose,
            'applied_at' => $l->applied_at?->toIso8601String(),
            'applicant' => [
                'full_name' => $l->user->full_name,
                'employee_id' => $l->user->employee_id,
                'employment_type' => $l->user->employment_type,
                'department' => $l->user->department,
            ],
        ]), 'Pending co-maker requests retrieved.');
    }

    /**
     * Co-maker approves or declines a loan (authenticated version of the email link).
     */
    public function respondCoMaker(Request $request, Loan $loan): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,decline',
        ]);

        if ($loan->co_maker_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        if ($loan->status !== 'co_maker_pending') {
            return $this->error('This loan is no longer waiting for your consent.', 422);
        }

        $loan->load(['user', 'coMaker']);

        if ($request->action === 'approve') {
            $nextStatus = $loan->requires_admin_approval ? 'admin_pending' : 'pending';

            $loan->update([
                'co_maker_status' => 'approved',
                'co_maker_acted_at' => now(),
                'co_maker_token' => null,
                'status' => $nextStatus,
            ]);

            $fresh = $loan->fresh(['user', 'coMaker']);
            if ($loan->requires_admin_approval) {
                $this->notificationService->notifyAdminApprovalRequired($fresh);
            } else {
                $this->notificationService->notifyNextApprovers($fresh);
            }
            $this->notificationService->notifyApplicant($loan, 'co_maker_approved', 'co_maker');

            return $this->success(null, 'You have agreed to be the co-maker. The loan is now under review.');
        }

        $loan->update([
            'co_maker_status' => 'declined',
            'co_maker_acted_at' => now(),
            'co_maker_token' => null,
            'status' => 'co_maker_declined',
        ]);

        $this->notificationService->notifyApplicant($loan, 'co_maker_declined', 'co_maker');

        return $this->success(null, 'You have declined. The applicant has been notified.');
    }

    /**
     * Get list of Permanent employees available as co-makers.
     * Supports search by name or employee_id.
     */
    public function coMakers(Request $request): JsonResponse
    {
        $query = User::where('employment_type', 'Permanent')
            ->where('status', 'active')
            ->where('id', '!=', $request->user()->id);

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'LIKE', "%{$s}%")
                  ->orWhere('last_name', 'LIKE', "%{$s}%")
                  ->orWhere('employee_id', 'LIKE', "%{$s}%");
            });
        }

        $coMakers = $query->orderBy('last_name')->take(20)->get();

        return $this->success($coMakers->map(fn (User $u) => [
            'value' => $u->id,
            'label' => "{$u->full_name} ({$u->employee_id})",
            'sublabel' => "{$u->department} — {$u->position}",
        ]), 'Co-makers retrieved.');
    }
}
