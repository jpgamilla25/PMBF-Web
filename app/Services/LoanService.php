<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Loan;
use App\Models\ShareCapital;
use App\Models\User;

class LoanService
{
    public function __construct(
        private readonly FmisService $fmisService,
        private readonly ExemptionService $exemptionService,
        private readonly LoanNotificationService $notificationService,
    ) {}

    /**
     * Get available loan types with max amounts from config.
     */
    public function getAvailableLoanTypes(User $user): array
    {
        $scMax = $this->fmisService->calculateScMaxLoan($user->employee_id);

        if ($user->isSC()) {
            $allTerms = $this->parseTerms(Configuration::getValue('sc_available_terms', '3,6,12'));
            $contractMonths = $scMax['contract_months'];
            // Only show terms that fit within contract duration
            $terms = array_values(array_filter($allTerms, fn ($t) => $t <= $contractMonths));
            if (empty($terms) && $contractMonths > 0) {
                $terms = [$contractMonths];
            }

            return [
                'Salary Loan' => [
                    'name' => 'Salary Loan',
                    'max_amount' => $scMax['max_loan_amount'],
                    'extended_max' => $scMax['extended_max'],
                    'monthly_salary' => $scMax['monthly_salary'],
                    'contract_months' => $contractMonths,
                    'contract_end' => $scMax['contract_end'],
                    'requires_co_maker' => Configuration::getBool('sc_requires_co_maker', true),
                    'can_request_extension' => Configuration::getBool('sc_can_extend_with_board_approval', true),
                    'available_terms' => $terms,
                    'interest_rate' => $this->getInterestRate($user),
                ],
            ];
        }

        if ($user->isPermanent()) {
            $terms = $this->parseTerms(Configuration::getValue('permanent_available_terms', '3,6,12,18,24,36,48,60'));
            $salary = $this->fmisService->getSalary($user->employee_id);
            $takeHome = $salary ? (float) $salary->net_take_home : null;
            $rate = $this->getInterestRate($user);

            return [
                'Consolidated' => [
                    'name' => 'Consolidated',
                    'max_amount' => Configuration::getDecimal('permanent_max_loan_consolidated', 200000),
                    'take_home_pay' => $takeHome,
                    'available_terms' => $terms,
                    'interest_rate' => $rate,
                ],
                'Multi-Purpose' => [
                    'name' => 'Multi-Purpose',
                    'max_amount' => Configuration::getDecimal('permanent_max_loan_multipurpose', 100000),
                    'take_home_pay' => $takeHome,
                    'available_terms' => $terms,
                    'interest_rate' => $rate,
                ],
                'Emergency' => [
                    'name' => 'Emergency',
                    'max_amount' => Configuration::getDecimal('permanent_max_loan_emergency', 30000),
                    'take_home_pay' => $takeHome,
                    'available_terms' => $terms,
                    'interest_rate' => $rate,
                ],
                'Hospitalization' => [
                    'name' => 'Hospitalization',
                    'max_amount' => Configuration::getDecimal('permanent_max_loan_hospitalization', 50000),
                    'take_home_pay' => $takeHome,
                    'available_terms' => $terms,
                    'interest_rate' => $rate,
                ],
            ];
        }

        // Non-Member
        $nonMax = Configuration::getDecimal('non_member_max_loan_amount', 30000);
        $terms = $this->parseTerms(Configuration::getValue('non_member_available_terms', '3,6,12,18,24'));
        $rate = $this->getInterestRate($user);
        return [
            'Salary Loan'   => ['name' => 'Salary Loan',   'max_amount' => $nonMax, 'available_terms' => $terms, 'interest_rate' => $rate],
            'Multi-Purpose' => ['name' => 'Multi-Purpose', 'max_amount' => $nonMax, 'available_terms' => $terms, 'interest_rate' => $rate],
            'Emergency'     => ['name' => 'Emergency',     'max_amount' => $nonMax, 'available_terms' => $terms, 'interest_rate' => $rate],
        ];
    }

    /**
     * Parse comma-separated term string into sorted int array.
     */
    private function parseTerms(string $terms): array
    {
        return collect(explode(',', $terms))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Full eligibility check using FMIS data + exemption overrides.
     *
     * Returns:
     *  - eligible: bool
     *  - message: string
     *  - can_request_exemption: bool (true if they can request override)
     *  - exemption_type: string|null
     *  - details: array (salary info, limits, etc.)
     */
    public function checkEligibility(User $user, string $loanType, ?float $requestedAmount = null): array
    {
        $types = $this->getAvailableLoanTypes($user);

        if (!isset($types[$loanType])) {
            return [
                'eligible' => false,
                'message' => 'This loan type is not available for your employment type.',
                'can_request_exemption' => false,
                'exemption_type' => null,
                'details' => [],
            ];
        }

        $typeConfig = $types[$loanType];
        $salary = $this->fmisService->getSalary($user->employee_id);
        $payCheck = $this->fmisService->meetsMinimumPay($user->employee_id, $user->employment_type);

        // ── Check 1: Minimum pay requirement ──────────────────
        if (!$payCheck['meets_requirement']) {
            // Check for active exemption
            if ($this->exemptionService->hasActiveExemption($user, 'below_minimum_pay', $loanType)) {
                // Exemption approved — allow to proceed
            } else {
                return [
                    'eligible' => false,
                    'message' => sprintf(
                        '%s members need at least ₱%s %s. Your FMIS record shows ₱%s.',
                        $user->employment_type,
                        number_format($payCheck['required_amount'], 2),
                        $payCheck['field_checked'] === 'net_take_home' ? 'net take-home pay' : 'base pay',
                        number_format($payCheck['actual_amount'], 2)
                    ),
                    'can_request_exemption' => true,
                    'exemption_type' => 'below_minimum_pay',
                    'details' => [
                        'current_value' => $payCheck['actual_amount'],
                        'required_value' => $payCheck['required_amount'],
                        'field' => $payCheck['field_checked'],
                        'salary_grade' => $salary?->salary_grade,
                    ],
                ];
            }
        }

        // ── Check 2: Max loan — above max requires admin approval (not a blocker) ──
        // We allow the loan through; the requires_admin_approval flag is set in create()

        // ── Check 3: Cannot apply if ANY loan is pending approval ──
        $hasPending = $user->loans()
            ->whereIn('status', ['co_maker_pending', 'admin_pending', 'pending', 'receiver_approved', 'committee_approved'])
            ->exists();

        if ($hasPending) {
            return [
                'eligible' => false,
                'message' => 'You have a loan application still pending approval. Please wait until it is processed before applying again.',
                'can_request_exemption' => false,
                'exemption_type' => null,
                'details' => [],
            ];
        }

        // ── Check 4: No duplicate active loan of same type ────────
        $hasActive = $user->loans()
            ->where('loan_type', $loanType)
            ->whereIn('status', ['approved', 'released'])
            ->exists();

        if ($hasActive) {
            return [
                'eligible' => false,
                'message' => "You already have an active {$loanType} loan.",
                'can_request_exemption' => false,
                'exemption_type' => null,
                'details' => [],
            ];
        }

        return [
            'eligible' => true,
            'message' => 'Eligible.',
            'can_request_exemption' => false,
            'exemption_type' => null,
            'details' => [
                'max_amount' => $typeConfig['max_amount'] ?? null,
                'monthly_salary' => $salary?->monthly_salary,
                'interest_rate' => $this->getInterestRate($user),
            ],
        ];
    }

    /**
     * Get interest rate from config based on employment type.
     */
    public function getInterestRate(User $user): float
    {
        if ($user->isSC()) return Configuration::getDecimal('interest_rate_sc', 1.50);
        if ($user->isPermanent()) return Configuration::getDecimal('interest_rate_permanent', 1.00);
        return Configuration::getDecimal('interest_rate_non_member', 2.00);
    }

    /**
     * Calculate monthly amortization (flat interest).
     */
    public function calculateAmortization(float $amount, float $monthlyRate, int $months): float
    {
        $totalInterest = $amount * ($monthlyRate / 100) * $months;
        return round(($amount + $totalInterest) / $months, 2);
    }

    /**
     * Is OTP required for loan applications?
     */
    public function isOtpRequired(): bool
    {
        return Configuration::getBool('loan_otp_required', true);
    }

    /**
     * Create a loan. Enforces max amounts and SC contract term.
     */
    public function create(User $user, array $data): Loan
    {
        $rate = $this->getInterestRate($user);
        $amount = (float) $data['amount'];
        $months = (int) $data['term_months'];

        // Determine max amount and whether admin approval is needed
        $types = $this->getAvailableLoanTypes($user);
        $typeConfig = $types[$data['loan_type']] ?? null;
        $maxAmount = $typeConfig['max_amount'] ?? PHP_FLOAT_MAX;
        $requiresAdminApproval = false;

        if ($amount > $maxAmount) {
            // Check if user has exemption to exceed
            $exemption = $this->exemptionService->getActiveExemption($user, 'exceed_max_amount', $data['loan_type']);
            if ($exemption) {
                $extendedMax = $typeConfig['extended_max'] ?? $maxAmount;
                $amount = min($amount, $extendedMax);
            } else {
                // No exemption — allow amount through but require admin approval
                $requiresAdminApproval = true;
            }
        }

        // SC: enforce contract-based term
        if ($user->isSC() && Configuration::getBool('sc_term_based_on_contract', true) && $user->contract_end) {
            $maxMonths = max(1, (int) now()->diffInMonths($user->contract_end));
            // If extend exemption approved, allow up to 12 months
            if ($months > $maxMonths) {
                $exemption = $this->exemptionService->getActiveExemption($user, 'extend_term', $data['loan_type']);
                $maxMonths = $exemption ? (int) Configuration::getValue('sc_extension_max_months', 12) : $maxMonths;
            }
            $months = min($months, $maxMonths);
        }

        $amortization = $this->calculateAmortization($amount, $rate, $months);

        $coMakerId = $data['co_maker_id'] ?? null;
        $needsCoMakerApproval = $user->isSC() && $coMakerId &&
            Configuration::getBool('sc_requires_co_maker', true);

        // Initial status: co_maker_pending if co-maker required, else admin_pending if above max, else pending
        if ($needsCoMakerApproval) {
            $initialStatus = 'co_maker_pending';
        } elseif ($requiresAdminApproval) {
            $initialStatus = 'admin_pending';
        } else {
            $initialStatus = 'pending';
        }

        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_type' => $data['loan_type'],
            'amount' => $amount,
            'purpose' => $data['purpose'],
            'interest_rate' => $rate,
            'term_months' => $months,
            'monthly_amortization' => $amortization,
            'co_maker_id' => $coMakerId,
            'co_maker_token' => $needsCoMakerApproval ? \Illuminate\Support\Str::random(48) : null,
            'co_maker_status' => $needsCoMakerApproval ? 'pending' : null,
            'requires_admin_approval' => $requiresAdminApproval,
            'status' => $initialStatus,
            'applied_at' => now(),
        ]);

        $loan->load('user', 'coMaker');

        if ($needsCoMakerApproval) {
            // Email co-maker for consent first — receivers/admin notified after co-maker agrees
            $this->notificationService->notifyCoMakerApprovalRequest($loan);
        } elseif ($requiresAdminApproval) {
            // Above max, no co-maker — go to admin first
            $this->notificationService->notifyAdminApprovalRequired($loan);
        } else {
            // Standard path — notify receivers
            $this->notificationService->notifyNextApprovers($loan);
        }

        return $loan;
    }

    /**
     * Get loan statistics for a user (member or staff).
     */
    public function getStats(User $user): array
    {
        $loans = $user->loans()->with('payments')->get();
        $active = $loans->whereIn('status', ['released', 'approved', 'chairperson_approved']);
        $pending = $loans->whereIn('status', ['co_maker_pending', 'admin_pending', 'pending', 'receiver_approved', 'committee_approved']);

        $totalPaid = $active->sum(fn (Loan $l) => $l->total_paid);
        $totalRemaining = $active->sum(fn (Loan $l) => $l->remaining_balance);

        // Next payment due info
        $nextPayment = null;
        foreach ($active as $loan) {
            if (!$loan->released_at) continue;
            $totalPayable = $loan->monthly_amortization * $loan->term_months;
            $loanPaid = $loan->payments->sum('amount');
            if ($loanPaid >= $totalPayable) continue;

            $paymentsMade = $loan->payments->count();
            $nextDue = $loan->released_at->copy()->addMonths($paymentsMade + 1);
            $daysUntil = now()->startOfDay()->diffInDays($nextDue, false);

            if (!$nextPayment || $nextDue->lt($nextPayment['date_raw'])) {
                $nextPayment = [
                    'loan_id' => $loan->id,
                    'loan_type' => $loan->loan_type,
                    'amount' => $loan->monthly_amortization,
                    'due_date' => $nextDue->format('M d, Y'),
                    'date_raw' => $nextDue,
                    'days_until' => (int) $daysUntil,
                    'is_overdue' => $daysUntil < 0,
                ];
            }
        }

        if ($nextPayment) {
            unset($nextPayment['date_raw']);
        }

        // Recent loans (last 5)
        $recentLoans = $user->loans()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Loan $l) => [
                'id' => $l->id,
                'loan_type' => $l->loan_type,
                'amount' => $l->amount,
                'status' => $l->status,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        // Staff: pending approvals count
        $pendingApprovals = 0;
        if ($user->isStaff()) {
            $statusForRole = match ($user->role) {
                'admin' => ['admin_pending', 'pending', 'receiver_approved', 'committee_approved', 'chairperson_approved'],
                'receiver' => ['pending', 'chairperson_approved'],
                'loan_committee' => ['receiver_approved'],
                'chairperson' => ['committee_approved'],
                default => [],
            };
            $pendingApprovals = Loan::whereIn('status', $statusForRole)->count();
        }

        return [
            'total_loans' => $loans->count(),
            'active_loans' => $active->count(),
            'pending_loans' => $pending->count(),
            'total_borrowed' => $active->sum('amount'),
            'total_paid' => $totalPaid,
            'total_remaining' => $totalRemaining,
            'next_payment' => $nextPayment,
            'recent_loans' => $recentLoans,
            'pending_approvals' => $pendingApprovals,
            'total_shares' => ShareCapital::where('user_id', $user->id)->sum('amount'),
            'current_monthly_share' => ShareCapital::where('user_id', $user->id)
                ->orderByDesc('year')->orderByDesc('month')->first()?->amount ?? 0,
        ];
    }
}
