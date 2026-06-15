<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\FmisLoanPayment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LinkFmisLoanPaymentsService
{
    /** Tolerance in pesos when comparing FMIS amount to a loan amortization. */
    private const DEFAULT_TOLERANCE = 1.00;

    /**
     * Walk every unlinked FMIS row and apply the matching decision tree.
     * Also reverse-applies any newly-voided FMIS rows that already produced
     * payments.
     *
     * @return array{linked: int, queued: int, voided_reversed: int, scanned: int}
     */
    public function linkPending(): array
    {
        $tolerance = (float) Configuration::getDecimal('fmis_link_tolerance', self::DEFAULT_TOLERANCE);
        $linked = $queued = $reversed = $scanned = 0;

        // 1) Reverse payments tied to FMIS rows that have since been voided.
        $reversed = $this->reverseVoidedLinks();

        // 2) Walk unlinked rows.
        FmisLoanPayment::query()
            ->whereNull('linked_at')
            ->where('voided', false)
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$linked, &$queued, &$scanned, $tolerance) {
                foreach ($rows as $row) {
                    $scanned++;
                    $allocations = $this->resolve($row, $tolerance);

                    if ($allocations === null) {
                        // Queue: leave linked_at null, admin will handle.
                        $queued++;
                        continue;
                    }

                    DB::transaction(function () use ($row, $allocations) {
                        foreach ($allocations as [$loan, $amount]) {
                            Payment::create([
                                'loan_id' => $loan->id,
                                'recorded_by' => null,
                                'amount' => $amount,
                                'or_number' => $row->dv_number,
                                'fmis_dv_number' => $row->dv_number,
                                'auto_linked' => true,
                                'payment_method' => 'payroll_deduction',
                                'payment_date' => $row->dv_date,
                                'remarks' => 'Auto-linked from FMIS DV ' . $row->dv_number . ' (' . $row->fund . ')',
                            ]);
                        }
                        $row->update(['linked_at' => now()]);
                    });

                    $linked++;
                }
            });

        return [
            'linked' => $linked,
            'queued' => $queued,
            'voided_reversed' => $reversed,
            'scanned' => $scanned,
        ];
    }

    /**
     * Apply the decision tree to one FMIS row and return the split (or null
     * to queue). Returns a list of [Loan, amount] pairs.
     *
     * @return list<array{0: Loan, 1: float}>|null
     */
    public function resolve(FmisLoanPayment $row, float $tolerance): ?array
    {
        $user = User::where('employee_id', $row->employee_id)->first();
        if (!$user) {
            return null; // Case E: unregistered employee.
        }

        $loans = $this->activeLoansFor($user);
        if ($loans->isEmpty()) {
            return null; // Case E: no active loans.
        }

        [$regulars, $emergencies] = $this->partition($loans);
        $fmisAmount = (float) $row->amount;

        // CASE A: exactly one active loan total.
        if ($loans->count() === 1) {
            $loan = $loans->first();
            return $this->within($fmisAmount, (float) $loan->monthly_amortization, $tolerance)
                ? [[$loan, $fmisAmount]]
                : null;
        }

        // CASE D: two or more regular loans — never auto-allocate.
        if ($regulars->count() >= 2) {
            return null;
        }

        // CASE B: one regular + one or more emergencies.
        if ($regulars->count() === 1 && $emergencies->count() >= 1) {
            $regular = $regulars->first();
            $regAmount = (float) $regular->monthly_amortization;
            $emerSum = (float) $emergencies->sum(fn (Loan $l) => (float) $l->monthly_amortization);

            // B.1: combined deduction (regular + every emergency).
            if ($this->within($fmisAmount, $regAmount + $emerSum, $tolerance)) {
                $split = [[$regular, $regAmount]];
                foreach ($emergencies as $e) {
                    $split[] = [$e, (float) $e->monthly_amortization];
                }
                return $split;
            }

            // B.2: regular only — emergencies skipped this period.
            if ($this->within($fmisAmount, $regAmount, $tolerance)) {
                return [[$regular, $fmisAmount]];
            }

            // B.3: emergencies only — regular skipped this period.
            if ($this->within($fmisAmount, $emerSum, $tolerance)) {
                $split = [];
                foreach ($emergencies as $e) {
                    $split[] = [$e, (float) $e->monthly_amortization];
                }
                return $split;
            }

            return null; // queue
        }

        // CASE C: no regular, multiple emergencies.
        if ($regulars->isEmpty() && $emergencies->count() >= 1) {
            $emerSum = (float) $emergencies->sum(fn (Loan $l) => (float) $l->monthly_amortization);
            if ($this->within($fmisAmount, $emerSum, $tolerance)) {
                $split = [];
                foreach ($emergencies as $e) {
                    $split[] = [$e, (float) $e->monthly_amortization];
                }
                return $split;
            }
        }

        return null;
    }

    /**
     * For a pending FMIS row, return the suggested split *and* the candidate
     * loans (even when it would queue) so the admin queue UI has context.
     *
     * @return array{
     *     suggestion: list<array{loan_id:int, loan_type:string, amount:float}>|null,
     *     active_loans: list<array{id:int, loan_type:string, amount:float, monthly_amortization:float, remaining:float, category:string}>,
     *     reason: string,
     * }
     */
    public function suggestionFor(FmisLoanPayment $row): array
    {
        $tolerance = (float) Configuration::getDecimal('fmis_link_tolerance', self::DEFAULT_TOLERANCE);
        $user = User::where('employee_id', $row->employee_id)->first();
        $loans = $user ? $this->activeLoansFor($user) : collect();

        $allocations = $user ? $this->resolve($row, $tolerance) : null;
        $reason = $this->reasonFor($user, $loans, $allocations, (float) $row->amount, $tolerance);

        return [
            'suggestion' => $allocations === null ? null : array_map(
                fn ($pair) => [
                    'loan_id' => $pair[0]->id,
                    'loan_type' => $pair[0]->loan_type,
                    'amount' => round($pair[1], 2),
                ],
                $allocations
            ),
            'active_loans' => $loans->map(fn (Loan $l) => [
                'id' => $l->id,
                'loan_type' => $l->loan_type,
                'amount' => (float) $l->amount,
                'monthly_amortization' => (float) $l->monthly_amortization,
                'remaining' => round($this->remainingFor($l), 2),
                'category' => LoanService::categoryOf($l->loan_type),
            ])->values()->all(),
            'reason' => $reason,
        ];
    }

    /**
     * Manually apply an FMIS row to a chosen split. The caller validates the
     * structure; we just persist + close the link.
     *
     * @param  list<array{loan_id:int, amount:float}>  $allocations
     */
    public function applyManual(FmisLoanPayment $row, array $allocations, ?int $reviewerUserId = null): void
    {
        DB::transaction(function () use ($row, $allocations, $reviewerUserId) {
            foreach ($allocations as $alloc) {
                $loan = Loan::findOrFail($alloc['loan_id']);
                Payment::create([
                    'loan_id' => $loan->id,
                    'recorded_by' => $reviewerUserId,
                    'amount' => $alloc['amount'],
                    'or_number' => $row->dv_number,
                    'fmis_dv_number' => $row->dv_number,
                    'auto_linked' => false,
                    'payment_method' => 'payroll_deduction',
                    'payment_date' => $row->dv_date,
                    'remarks' => 'Manually allocated from FMIS DV ' . $row->dv_number . ' (' . $row->fund . ')',
                ]);
            }
            $row->update(['linked_at' => now()]);
        });
    }

    /**
     * Walk FMIS rows that were marked voided after we already linked them,
     * delete the auto-linked payments, and clear the linked_at marker.
     */
    private function reverseVoidedLinks(): int
    {
        $count = 0;

        FmisLoanPayment::query()
            ->whereNotNull('linked_at')
            ->where('voided', true)
            ->chunk(200, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row) {
                        Payment::where('fmis_dv_number', $row->dv_number)->delete();
                        $row->update(['linked_at' => null]);
                    });
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return Collection<int, Loan>
     */
    private function activeLoansFor(User $user): Collection
    {
        return Loan::where('user_id', $user->id)
            ->where('status', 'released')
            ->get()
            ->reject(fn (Loan $loan) => $this->isFullyPaid($loan))
            ->values();
    }

    /**
     * @param  Collection<int, Loan>  $loans
     * @return array{0: Collection<int, Loan>, 1: Collection<int, Loan>}
     */
    private function partition(Collection $loans): array
    {
        $regulars = $loans->filter(fn (Loan $l) => LoanService::categoryOf($l->loan_type) === 'regular')->values();
        $emergencies = $loans->filter(fn (Loan $l) => LoanService::categoryOf($l->loan_type) === 'emergency')->values();

        return [$regulars, $emergencies];
    }

    private function isFullyPaid(Loan $loan): bool
    {
        return $this->remainingFor($loan) <= 0.005;
    }

    private function remainingFor(Loan $loan): float
    {
        $totalPayable = (float) $loan->monthly_amortization * (int) $loan->term_months;
        $paid = (float) $loan->payments()->sum('amount');
        return max(0.0, round($totalPayable - $paid, 2));
    }

    private function within(float $a, float $b, float $tolerance): bool
    {
        return abs($a - $b) <= $tolerance;
    }

    private function reasonFor(?User $user, Collection $loans, ?array $allocations, float $fmisAmount, float $tolerance): string
    {
        if (!$user) {
            return 'Employee is not registered in PMBF.';
        }
        if ($loans->isEmpty()) {
            return 'Member has no active released loans.';
        }
        if ($allocations !== null) {
            return 'Auto-matched by rule.';
        }

        [$regulars, $emergencies] = $this->partition($loans);

        if ($regulars->count() >= 2) {
            return 'Member has multiple regular loans — choose which one to apply to.';
        }

        $expected = (float) $loans->sum(fn (Loan $l) => (float) $l->monthly_amortization);
        $diff = round($fmisAmount - $expected, 2);

        if ($diff !== 0.0) {
            return sprintf(
                'FMIS amount ₱%s differs from combined amortization ₱%s by ₱%s (tolerance ±₱%.2f).',
                number_format($fmisAmount, 2),
                number_format($expected, 2),
                number_format($diff, 2),
                $tolerance
            );
        }

        return 'Needs manual review.';
    }
}
