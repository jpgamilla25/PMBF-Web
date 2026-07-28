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
     * Effective employment type — sourced from HRIS (with local fallback) so the
     * loan flow follows the live record, not a stale users-table snapshot.
     */
    private function employmentType(User $user): string
    {
        return $this->fmisService->getEmploymentType($user->employee_id) ?? (string) $user->employment_type;
    }

    private function isSC(User $user): bool
    {
        return $this->employmentType($user) === 'Contract of Service';
    }

    private function isPermanent(User $user): bool
    {
        return $this->employmentType($user) === 'Permanent';
    }

    /**
     * Contract end date from HRIS (with local fallback).
     */
    private function contractEnd(User $user): ?\Carbon\Carbon
    {
        return $this->fmisService->getContractEnd($user->employee_id);
    }

    /**
     * Get available loan types with max amounts from config.
     */
    public function getAvailableLoanTypes(User $user): array
    {
        $scMax = $this->fmisService->calculateScMaxLoan($user->employee_id);

        if ($this->isSC($user)) {
            $allTerms = $this->parseTerms(Configuration::getValue('sc_available_terms', '3,6,12'));
            $contractMonths = $scMax['contract_months'];
            $remainingMonths = $scMax['remaining_contract_months'];
            // Only show terms that fit within the REMAINING contract — a member
            // can't commit to a 12-month loan when only 4 months are left.
            $terms = array_values(array_filter($allTerms, fn ($t) => $t <= $remainingMonths));
            if (empty($terms) && $remainingMonths > 0) {
                $terms = [$remainingMonths];
            }

            return [
                'Salary Loan' => [
                    'name' => 'Salary Loan',
                    'max_amount' => $scMax['max_loan_amount'],
                    'extended_max' => $scMax['extended_max'],
                    'monthly_salary' => $scMax['monthly_salary'],
                    'contract_months' => $contractMonths,
                    'remaining_contract_months' => $remainingMonths,
                    'contract_end' => $scMax['contract_end'],
                    'requires_co_maker' => Configuration::getBool('sc_requires_co_maker', true),
                    'can_request_extension' => Configuration::getBool('sc_can_extend_with_board_approval', true),
                    'available_terms' => $terms,
                    'interest_rate' => $this->getInterestRate($user, 'Salary Loan'),
                    'interest_method' => $this->getInterestMethod($user),
                ],
            ];
        }

        if ($this->isPermanent($user)) {
            $terms = $this->parseTerms(Configuration::getValue('permanent_available_terms', '3,6,12,18,24,36,48,60'));
            $salary = $this->fmisService->getSalary($user->employee_id);
            $takeHome = $salary ? (float) $salary->net_take_home : null;

            // Each type carries its own rate so the application screen and the
            // stored loan agree with whatever the admin configured.
            $permanentTypes = [
                'Consolidated' => ['permanent_max_loan_consolidated', 200000],
                'Multi-Purpose' => ['permanent_max_loan_multipurpose', 100000],
                'Emergency' => ['permanent_max_loan_emergency', 30000],
                'Hospitalization' => ['permanent_max_loan_hospitalization', 50000],
            ];

            $result = [];
            foreach ($permanentTypes as $name => [$maxKey, $maxDefault]) {
                $result[$name] = [
                    'name' => $name,
                    'max_amount' => Configuration::getDecimal($maxKey, $maxDefault),
                    'take_home_pay' => $takeHome,
                    'available_terms' => $terms,
                    'interest_rate' => $this->getInterestRate($user, $name),
                    'interest_method' => $this->getInterestMethod($user),
                ];
            }

            return $result;
        }

        // Non-Member
        $nonMax = Configuration::getDecimal('non_member_max_loan_amount', 30000);
        $terms = $this->parseTerms(Configuration::getValue('non_member_available_terms', '3,6,12,18,24'));

        $result = [];
        foreach (['Salary Loan', 'Multi-Purpose', 'Emergency'] as $name) {
            $result[$name] = [
                'name' => $name,
                'max_amount' => $nonMax,
                'available_terms' => $terms,
                'interest_rate' => $this->getInterestRate($user, $name),
                'interest_method' => $this->getInterestMethod($user),
            ];
        }

        return $result;
    }

    /**
     * Parse comma-separated term string into sorted int array.
     */
    private function parseTerms(string $terms): array
    {
        return collect(explode(',', $terms))
            // Terms are granular to the half-month (e.g. 5.5), so parse as
            // float rather than int — casting to int would turn 5.5 into 5,
            // then the dedupe would drop it as a duplicate of an existing 5.
            ->map(fn ($v) => FmisService::usableTermMonths((float) trim($v)))
            ->filter(fn ($v) => $v > 0)
            // A duplicated entry in the config would otherwise render the
            // same option twice in the term dropdown.
            ->unique()
            ->sort()
            ->values()
            // 5.0 → 5 for a clean "5 months" label; 5.5 stays 5.5.
            ->map(fn ($v) => $v == (int) $v ? (int) $v : $v)
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
    public function checkEligibility(User $user, string $loanType, ?float $requestedAmount = null, ?int $requestedTerm = null): array
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
        $employmentType = $this->employmentType($user);
        $contractEnd = $this->contractEnd($user);
        $salary = $this->fmisService->getSalary($user->employee_id);
        $payCheck = $this->fmisService->meetsMinimumPay($user->employee_id, $employmentType);

        // ── Check 0: Contract of Service term must fit remaining contract ──
        if ($this->isSC($user) && Configuration::getBool('sc_term_based_on_contract', true) && $contractEnd) {
            $remainingMonths = max(0, FmisService::usableTermMonths(now()->floatDiffInMonths($contractEnd, false)));

            if ($remainingMonths === 0) {
                return [
                    'eligible' => false,
                    'message' => 'Your contract has expired or ends today. A new salary loan cannot be released.',
                    'can_request_exemption' => false,
                    'exemption_type' => null,
                    'details' => ['remaining_contract_months' => 0],
                ];
            }

            if ($requestedTerm !== null && $requestedTerm > $remainingMonths) {
                $hasExtensionExemption = $this->exemptionService->hasActiveExemption($user, 'extend_term', $loanType);
                if (!$hasExtensionExemption) {
                    return [
                        'eligible' => false,
                        'message' => sprintf(
                            'Term of %d months exceeds your remaining contract (%d month%s). Pick a shorter term or request a board extension.',
                            $requestedTerm,
                            $remainingMonths,
                            $remainingMonths === 1 ? '' : 's'
                        ),
                        'can_request_exemption' => Configuration::getBool('sc_can_extend_with_board_approval', true),
                        'exemption_type' => 'extend_term',
                        'details' => [
                            'requested_term' => $requestedTerm,
                            'remaining_contract_months' => $remainingMonths,
                            'extension_cap_months' => (int) Configuration::getValue('sc_extension_max_months', 12),
                        ],
                    ];
                }
            }
        }

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
                        $employmentType,
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
                'interest_rate' => $this->getInterestRate($user, $loanType),
            ],
        ];
    }

    /**
     * Monthly interest rate for a member, optionally for a specific loan type.
     *
     * Rates resolve most-specific-first:
     *   1. interest_rate_{member_type}_{loan_type}  e.g. interest_rate_permanent_emergency
     *   2. interest_rate_{member_type}              e.g. interest_rate_permanent
     *
     * A per-type key that is absent or left blank falls through to the
     * member-type rate, so an admin can override selectively without having to
     * fill in every loan type.
     */
    public function getInterestRate(User $user, ?string $loanType = null): float
    {
        [$scope, $default] = match (true) {
            $this->isSC($user) => ['sc', 1.50],
            $this->isPermanent($user) => ['permanent', 1.00],
            default => ['non_member', 2.00],
        };

        $rate = null;

        if ($loanType !== null) {
            $override = Configuration::getValue(self::interestRateKey($scope, $loanType));

            if ($override !== null && trim((string) $override) !== '') {
                $rate = (float) $override;
            }
        }

        $rate ??= Configuration::getDecimal("interest_rate_{$scope}", $default);

        // Rates may be configured per year; the schedule always runs on a
        // monthly rate, so convert 8% p.a. → 0.6667%/month with no rounding.
        if (Configuration::getValue("interest_period_{$scope}") === 'per_annum') {
            $rate /= 12;
        }

        return $rate;
    }

    /**
     * Config key for a per-loan-type rate, e.g. ('permanent', 'Multi-Purpose')
     * becomes interest_rate_permanent_multi_purpose.
     */
    public static function interestRateKey(string $scope, string $loanType): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $loanType));

        return "interest_rate_{$scope}_" . trim($slug, '_');
    }

    /**
     * Interest method for a member — 'flat' or 'diminishing' — from config,
     * defaulting Permanent to diminishing and everyone else to flat.
     */
    public function getInterestMethod(User $user): string
    {
        $scope = match (true) {
            $this->isSC($user) => 'sc',
            $this->isPermanent($user) => 'permanent',
            default => 'non_member',
        };

        $default = $scope === 'permanent' ? 'diminishing' : 'flat';
        $method = strtolower((string) Configuration::getValue("interest_method_{$scope}", $default));

        return $method === 'diminishing' ? 'diminishing' : 'flat';
    }

    /**
     * The regular monthly payment for a loan.
     *
     * Flat: (principal + flat interest) / term.
     * Diminishing: the EMI (annuity) payment, interest charged on the reducing
     * balance each period.
     */
    public function calculateAmortization(float $amount, float $monthlyRate, float $months, string $method = 'flat'): float
    {
        $figures = $this->loanFigures($amount, $monthlyRate, $months, $method);

        return $figures['monthly'];
    }

    /**
     * The per-period breakdown, whichever method applies. Both the schedule
     * and the stored totals derive from this one place, so they can never
     * disagree.
     *
     * Guarantees: principal parts sum exactly to the principal, and the final
     * period absorbs any rounding residual.
     *
     * @return array<int, array{interest: float, principal: float, total_due: float, balance: float}>
     */
    public function installments(float $amount, float $ratePct, float $months, string $method): array
    {
        if ($amount <= 0 || $months <= 0) {
            return [];
        }

        $r = $ratePct / 100;
        $periods = (int) ceil($months);
        $rows = [];
        $balance = round($amount, 2);

        if ($method === 'diminishing' && $r > 0) {
            // EMI over the (possibly fractional) term.
            $pow = (1 + $r) ** $months;
            $emi = round($amount * $r * $pow / ($pow - 1), 2);

            for ($i = 1; $i <= $periods; $i++) {
                $interest = round($balance * $r, 2);

                if ($i === $periods) {
                    $principal = $balance;               // clear the balance
                } else {
                    $principal = round($emi - $interest, 2);
                    $principal = max(0.0, min($principal, $balance));
                }

                $due = round($principal + $interest, 2);
                $balance = round($balance - $principal, 2);

                $rows[] = ['interest' => $interest, 'principal' => $principal, 'total_due' => $due, 'balance' => max(0.0, $balance)];
            }

            return $rows;
        }

        // Flat: interest is a constant slice on the full principal; the payment
        // is constant and the final period absorbs rounding.
        $total = round($amount + $amount * $r * $months, 2);
        $monthly = round($total / $months, 2);
        $interestPerPeriod = round($amount * $r, 2);
        $paidSoFar = 0.0;

        for ($i = 1; $i <= $periods; $i++) {
            $due = $i < $periods ? $monthly : round($total - $monthly * ($periods - 1), 2);
            $principal = round($due - $interestPerPeriod, 2);
            $paidSoFar = round($paidSoFar + $due, 2);
            $balance = max(0.0, round($total - $paidSoFar, 2));

            $rows[] = ['interest' => $interestPerPeriod, 'principal' => $principal, 'total_due' => $due, 'balance' => $balance];
        }

        return $rows;
    }

    /**
     * Regular monthly payment + exact total payable for a loan.
     *
     * @return array{monthly: float, total: float}
     */
    public function loanFigures(float $amount, float $ratePct, float $months, string $method): array
    {
        if ($months <= 0 || $amount <= 0) {
            return ['monthly' => 0.0, 'total' => round(max(0, $amount), 2)];
        }

        $rows = $this->installments($amount, $ratePct, $months, $method);
        $total = round(array_sum(array_column($rows, 'total_due')), 2);

        // The "monthly" figure is the regular full-period payment, i.e. the
        // first period (the last may be a smaller rounding/partial period).
        $monthly = $rows[0]['total_due'] ?? 0.0;

        return ['monthly' => round($monthly, 2), 'total' => $total];
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
        // Rate is resolved for THIS loan type, so a per-type override in config
        // is what actually gets stored on the loan. Round to the column's 4-dp
        // precision up front (a per-annum rate like 8/12 is non-terminating), so
        // the stored amortization matches the schedule, which recomputes from
        // the persisted rate.
        $rate = round($this->getInterestRate($user, $data['loan_type'] ?? null), 4);
        $amount = (float) $data['amount'];
        // Float, not int — a 5.5-month term must survive to the stored loan.
        $months = (float) $data['term_months'];

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

        // Contract of Service: hard-reject when term exceeds remaining contract
        // months unless an extend_term exemption is active for this loan type.
        if ($this->isSC($user) && Configuration::getBool('sc_term_based_on_contract', true) && ($contractEnd = $this->contractEnd($user))) {
            $remainingMonths = max(0, FmisService::usableTermMonths(now()->floatDiffInMonths($contractEnd, false)));

            if ($months > $remainingMonths) {
                $exemption = $this->exemptionService->getActiveExemption($user, 'extend_term', $data['loan_type']);
                if (!$exemption) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'term_months' => sprintf(
                            'Term of %d months exceeds your remaining contract (%d month%s). Choose a shorter term or request a board extension.',
                            $months,
                            $remainingMonths,
                            $remainingMonths === 1 ? '' : 's'
                        ),
                    ]);
                }
                $extensionCap = (int) Configuration::getValue('sc_extension_max_months', 12);
                if ($months > $extensionCap) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'term_months' => sprintf(
                            'Even with a board-approved extension, the maximum term is %d months.',
                            $extensionCap
                        ),
                    ]);
                }
            }
        }

        // Interest method + exact figures, locked onto the loan at creation.
        $method = $this->getInterestMethod($user);
        $figures = $this->loanFigures($amount, $rate, $months, $method);
        $amortization = $figures['monthly'];

        // Permanent members may request a future start month (normalised to the
        // first of that month). A future month is a special request the
        // administrator approves first, so route it to admin_pending.
        $startDate = null;
        if ($this->isPermanent($user) && !empty($data['start_date'])) {
            $startDate = \Carbon\Carbon::parse($data['start_date'])->startOfMonth()->toDateString();
            if (\Carbon\Carbon::parse($startDate)->gt(now()->endOfMonth())) {
                $requiresAdminApproval = true;
            }
        }

        // Co-makers: an array (COS-Enrolled may name up to two), falling back
        // to the single co_maker_id for older callers. First → slot 1,
        // second → slot 2.
        $coMakerIds = array_values(array_filter(
            $data['co_maker_ids'] ?? [$data['co_maker_id'] ?? null]
        ));
        $coMakerIds = array_slice(array_unique($coMakerIds), 0, 2);
        $coMakerId = $coMakerIds[0] ?? null;
        $coMakerId2 = $coMakerIds[1] ?? null;

        $needsCoMakerApproval = $this->isSC($user) && $coMakerId &&
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
            'interest_method' => $method,
            'term_months' => $months,
            'start_date' => $startDate,
            'renewed_from_loan_id' => $data['renewed_from_loan_id'] ?? null,
            'monthly_amortization' => $amortization,
            'total_payable' => $figures['total'],
            'co_maker_id' => $coMakerId,
            'co_maker_token' => $needsCoMakerApproval ? \Illuminate\Support\Str::random(48) : null,
            'co_maker_status' => $needsCoMakerApproval ? 'pending' : null,
            'co_maker_id_2' => $coMakerId2,
            'co_maker_token_2' => ($needsCoMakerApproval && $coMakerId2) ? \Illuminate\Support\Str::random(48) : null,
            'co_maker_status_2' => ($needsCoMakerApproval && $coMakerId2) ? 'pending' : null,
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
     * Whether a loan can be renewed: active (approved/released), not fully
     * paid, and not already superseded by a renewal.
     */
    public function canRenew(Loan $loan): bool
    {
        return in_array($loan->status, ['approved', 'released'], true)
            && $loan->remaining_balance > 0.01
            && !Loan::where('renewed_from_loan_id', $loan->id)
                ->whereNotIn('status', ['disapproved', 'cancelled'])
                ->exists();
    }

    /**
     * Renew a loan: refinance its remaining balance (optionally plus a top-up)
     * into a new loan of the same type with a fresh term. The original is
     * settled only when the renewal is released — see ApprovalController —
     * so a rejected renewal leaves the member's existing loan intact.
     *
     * @param  array{term_months: mixed, additional_amount?: mixed, purpose?: string, start_date?: string, co_maker_id?: mixed}  $data
     */
    public function renew(User $user, Loan $original, array $data): Loan
    {
        $remaining = round($original->remaining_balance, 2);
        $topUp = max(0, (float) ($data['additional_amount'] ?? 0));
        $principal = round($remaining + $topUp, 2);

        return $this->create($user, [
            'loan_type' => $original->loan_type,
            'amount' => $principal,
            'term_months' => $data['term_months'],
            'purpose' => $data['purpose']
                ?? "Renewal of {$original->reference_no} (₱" . number_format($remaining, 2) . ' outstanding'
                    . ($topUp > 0 ? ' + ₱' . number_format($topUp, 2) . ' additional' : '') . ')',
            'start_date' => $data['start_date'] ?? null,
            'co_maker_id' => $data['co_maker_id'] ?? $original->co_maker_id,
            'renewed_from_loan_id' => $original->id,
        ]);
    }

    /**
     * Mark the original loan as renewed once its renewal loan is released.
     * Its remaining balance is now owed through the new loan.
     */
    public function settleRenewedParent(Loan $renewalLoan): void
    {
        if (!$renewalLoan->renewed_from_loan_id) {
            return;
        }

        Loan::where('id', $renewalLoan->renewed_from_loan_id)
            ->whereIn('status', ['approved', 'released'])
            ->update(['status' => 'renewed']);
    }

    /**
     * Build the flat-interest amortization schedule for a loan plus a progress
     * summary ("payment 7 of 24"). Interest is fixed each month
     * (principal × rate%); the final installment absorbs the per-month rounding
     * so the schedule sums exactly to the total payable.
     *
     * Recorded payments are applied against installments in order, so a member
     * sees which periods are settled, partially settled, or overdue.
     *
     * @return array{schedule: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function amortizationSchedule(Loan $loan): array
    {
        $loan->loadMissing('payments');

        $amount = (float) $loan->amount;
        $rate = (float) $loan->interest_rate;
        $method = $loan->interest_method ?? 'flat';
        // Exact term for the maths; a fractional term (5.5) becomes ceil()
        // payment periods where the last one is partial.
        $termExact = max(0.5, (float) $loan->term_months);
        $term = (int) ceil($termExact);
        $monthly = (float) $loan->monthly_amortization;
        $totalPayable = $loan->total_payable;
        $totalPaid = round((float) $loan->payments->sum('amount'), 2);

        // Per-period principal/interest split — flat or diminishing.
        $breakdown = $this->installments($amount, $rate, $termExact, $method);

        // A requested future start month anchors the schedule; otherwise the
        // first deduction follows release (then application) date.
        $base = $loan->start_date
            ? $loan->start_date->copy()->subMonth()
            : ($loan->released_at ?? $loan->applied_at ?? $loan->created_at);

        $isReleased = (bool) $loan->released_at;
        $today = now()->startOfDay();

        $rows = [];
        $cumulative = 0.0;
        $periodsPaid = 0;
        $overdueCount = 0;
        $next = null;

        for ($i = 1; $i <= $term; $i++) {
            $row = $breakdown[$i - 1] ?? ['interest' => 0.0, 'principal' => 0.0, 'total_due' => 0.0, 'balance' => 0.0];
            $installment = $row['total_due'];

            $cumulativeBefore = $cumulative;
            $cumulative = round($cumulative + $installment, 2);
            // Outstanding *principal* after this period — the breakdown already
            // amortises it (subtracting only the principal portion), so use that
            // rather than draining the full total-payable by each installment.
            $balance = $row['balance'] ?? max(0, round($totalPayable - $cumulative, 2));
            $dueDate = $base ? $base->copy()->addMonths($i) : null;

            // How much of this installment the recorded payments cover.
            $appliedToPeriod = max(0.0, min($installment, round($totalPaid - $cumulativeBefore, 2)));

            if ($totalPaid + 0.01 >= $cumulative) {
                $status = 'paid';
                $periodsPaid++;
            } elseif ($totalPaid > $cumulativeBefore + 0.01) {
                $status = 'partial';
            } elseif (!$isReleased) {
                $status = 'scheduled';
            } elseif ($dueDate && $dueDate->lt($today)) {
                $status = 'overdue';
            } else {
                $status = 'upcoming';
            }

            if ($status === 'overdue') {
                $overdueCount++;
            }

            if ($next === null && $status !== 'paid') {
                $next = [
                    'period' => $i,
                    'due_date' => $dueDate?->toDateString(),
                    'due_date_label' => $dueDate?->format('M d, Y'),
                    'amount' => round($installment - $appliedToPeriod, 2),
                    'full_amount' => $installment,
                    'is_overdue' => $status === 'overdue',
                    'days_until' => $dueDate ? (int) $today->diffInDays($dueDate, false) : null,
                ];
            }

            $rows[] = [
                'period' => $i,
                // Carbon so the PDF view can format it; JSON-encodes to ISO-8601.
                'due_date' => $dueDate,
                'due_date_label' => $dueDate?->format('M d, Y'),
                'principal' => $row['principal'],
                'interest' => $row['interest'],
                'total_due' => $installment,
                'paid_amount' => $appliedToPeriod,
                'balance' => $balance,
                'status' => $status,
            ];
        }

        $totalRemaining = max(0, round($totalPayable - $totalPaid, 2));

        return [
            'schedule' => $rows,
            'summary' => [
                'loan_id' => $loan->id,
                'loan_type' => $loan->loan_type,
                'principal' => round($amount, 2),
                'interest_rate' => $rate,
                'term_months' => $term,
                'monthly_amortization' => $monthly,
                'total_payable' => $totalPayable,
                'total_interest' => round($totalPayable - $amount, 2),
                'periods_total' => $term,
                'periods_paid' => $periodsPaid,
                'periods_remaining' => $term - $periodsPaid,
                'periods_overdue' => $overdueCount,
                'next_due' => $next,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining,
                'percent_complete' => $totalPayable > 0
                    ? min(100, round(($totalPaid / $totalPayable) * 100, 1))
                    : 0.0,
                'is_released' => $isReleased,
                'released_at' => $loan->released_at?->toDateString(),
                'first_due_date' => $rows[0]['due_date']?->toDateString(),
                'final_due_date' => $rows[$term - 1]['due_date']?->toDateString(),
            ],
        ];
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
            $totalPayable = $loan->total_payable;
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
            // Contribution stats — surface shares for Permanent, premiums for
            // Contract of Service. Type discriminates the same share_capitals
            // table, so a promoted member's dashboard flips over cleanly:
            // Permanent tile shows only new type='share' rows; historical
            // type='premium' rows are still visible on /premiums/my.
            ...($user->employment_type === 'Contract of Service'
                ? [
                    'total_premium' => ShareCapital::where('user_id', $user->id)
                        ->where('type', 'premium')
                        ->sum('amount'),
                    'current_monthly_premium' => ShareCapital::where('user_id', $user->id)
                        ->where('type', 'premium')
                        ->orderByDesc('year')->orderByDesc('month')->first()?->amount ?? 0,
                ]
                : [
                    'total_shares' => ShareCapital::where('user_id', $user->id)
                        ->where('type', 'share')
                        ->sum('amount'),
                    'current_monthly_share' => ShareCapital::where('user_id', $user->id)
                        ->where('type', 'share')
                        ->orderByDesc('year')->orderByDesc('month')->first()?->amount ?? 0,
                ]),
        ];
    }
}
