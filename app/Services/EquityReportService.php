<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Dependent;
use App\Models\DependentPremium;
use App\Models\DependentPremiumPayment;
use App\Models\Loan;
use App\Models\MemberCounterpart;
use App\Models\ShareCapital;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the three schedules in the PMBF year-end report for Permanent
 * (regular) members — the same sheets as the office's Excel template:
 *
 *  1. Equity & Loans      — personal contribution + PhilRice counterpart,
 *                           less outstanding loans by type, as of year end.
 *  2. Medical for Dep     — dependents' health premium and monthly deductions.
 *  3. Dependents & Benef. — each member's declared dependents and beneficiaries.
 */
class EquityReportService
{
    public const MEMBER_TYPE = 'Permanent';

    /**
     * Report column => loan types folded into it. Regular is the retired
     * duplicate of Consolidated, so it lands in CONSO; anything not named
     * in the template goes to Others.
     */
    public const LOAN_COLUMNS = [
        'conso'            => ['Consolidated', 'Regular'],
        'hospitalization'  => ['Hospitalization'],
        'palay_production' => ['Palay Production'],
        'emergency'        => ['Emergency'],
        'multi_purpose'    => ['Multi-Purpose'],
        'others'           => ['Salary Loan', 'Temporary'],
    ];

    public const LOAN_LABELS = [
        'conso'            => 'CONSO',
        'hospitalization'  => 'Hospitalization',
        'palay_production' => 'Palay Production',
        'emergency'        => 'Emergency',
        'multi_purpose'    => 'Multi-purpose',
        'others'           => 'Others',
    ];

    /** Loans that were actually disbursed and so may carry a balance. */
    private const DISBURSED_STATUSES = ['released', 'completed', 'defaulted', 'renewed'];

    public function build(int $year): array
    {
        return [
            'year'         => $year,
            'equity'       => $this->equity($year),
            'medical'      => $this->medical($year),
            'dependents'   => $this->dependents(),
            'loan_columns' => self::LOAN_LABELS,
        ];
    }

    // ── 1. Equity & Loans ─────────────────────────────────────────

    public function equity(int $year): array
    {
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();
        $members = $this->members();
        $ids = $members->pluck('id');

        // Equity is cumulative: everything contributed up to the year's end.
        $personal = ShareCapital::whereIn('user_id', $ids)
            ->where('type', 'share')
            ->where('year', '<=', $year)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');

        $counterpart = MemberCounterpart::whereIn('user_id', $ids)
            ->where('year', '<=', $year)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total')
            ->pluck('total', 'user_id');


        $loans = Loan::whereIn('user_id', $ids)
            ->whereIn('status', self::DISBURSED_STATUSES)
            ->with(['payments' => fn ($q) => $q->where('payment_date', '<=', $yearEnd->toDateString())])
            ->get();

        // A renewal closes the loan it replaced; when did that happen?
        $renewedOn = Loan::whereIn('renewed_from_loan_id', $loans->pluck('id'))
            ->get()
            ->mapWithKeys(fn (Loan $r) => [$r->renewed_from_loan_id => $this->disbursedOn($r)]);

        $loansByUser = $loans->groupBy('user_id');

        $rows = $members->values()->map(function (User $u, int $i) use ($personal, $counterpart, $loansByUser, $renewedOn, $yearEnd) {
            $balances = array_fill_keys(array_keys(self::LOAN_COLUMNS), 0.0);

            foreach ($loansByUser->get($u->id, collect()) as $loan) {
                $column = $this->loanColumn($loan->loan_type);
                $balances[$column] += $this->balanceAsOf($loan, $yearEnd, $renewedOn->get($loan->id));
            }
            $balances = array_map(fn ($v) => round($v, 2), $balances);

            $p = round((float) ($personal[$u->id] ?? 0), 2);
            $c = round((float) ($counterpart[$u->id] ?? 0), 2);
            $totalLoans = round(array_sum($balances), 2);

            return [
                'no'                  => $i + 1,
                'user_id'             => $u->id,
                'employee_id'         => $u->employee_id,
                'name'                => $this->displayName($u),
                'personal'            => $p,
                'counterpart'         => $c,
                'total_equity'        => round($p + $c, 2),
                'loans'               => $balances,
                'total_loans'         => $totalLoans,
                'net_equity'          => round($p + $c - $totalLoans, 2),
            ];
        });

        $totals = [
            'personal'     => round($rows->sum('personal'), 2),
            'counterpart'  => round($rows->sum('counterpart'), 2),
            'total_equity' => round($rows->sum('total_equity'), 2),
            'loans'        => collect(array_keys(self::LOAN_COLUMNS))
                ->mapWithKeys(fn ($k) => [$k => round($rows->sum(fn ($r) => $r['loans'][$k]), 2)])
                ->all(),
            'total_loans'  => round($rows->sum('total_loans'), 2),
            'net_equity'   => round($rows->sum('net_equity'), 2),
        ];

        return ['rows' => $rows->all(), 'totals' => $totals];
    }

    private function loanColumn(string $type): string
    {
        foreach (self::LOAN_COLUMNS as $column => $types) {
            if (in_array($type, $types, true)) {
                return $column;
            }
        }

        return 'others';
    }

    private function disbursedOn(Loan $loan): Carbon
    {
        return Carbon::parse($loan->released_at ?? $loan->start_date ?? $loan->created_at);
    }

    /**
     * What was still owed on a loan at $asOf: total payable less payments made
     * by then (the relation is pre-filtered to that date). Zero if it wasn't
     * disbursed yet, or had already been rolled into a renewal.
     */
    private function balanceAsOf(Loan $loan, Carbon $asOf, ?Carbon $renewedOn): float
    {
        if ($this->disbursedOn($loan)->gt($asOf)) {
            return 0.0;
        }
        if ($renewedOn && $renewedOn->lte($asOf)) {
            return 0.0;
        }

        $paid = (float) $loan->payments->sum('amount');

        return max(0.0, (float) $loan->total_payable - $paid);
    }

    // ── 2. Medical for Dependents ─────────────────────────────────

    public function medical(int $year): array
    {
        $members = $this->members()->keyBy('id');

        $premiums = DependentPremium::where('year', $year)->get()->keyBy('dependent_id');
        $payments = DependentPremiumPayment::where('year', $year)->get()->groupBy('dependent_id');

        // Covered dependents, plus anyone who already has a premium on record
        // this year (coverage may have been removed since).
        $dependents = Dependent::whereIn('user_id', $members->keys())
            ->where(fn ($q) => $q->whereNotNull('coverage_type')
                ->orWhereIn('id', $premiums->keys())
                ->orWhereIn('id', $payments->keys()))
            ->get()
            ->sortBy(fn (Dependent $d) => [$this->displayName($members[$d->user_id]), $d->id]);

        $defaultPremium = $this->defaultPremium();
        $installments = $this->installments();

        $rows = $dependents->values()->map(function (Dependent $d) use ($members, $premiums, $payments, $defaultPremium, $installments) {
            $record = $premiums->get($d->id);
            $premium = $record ? (float) $record->premium : $defaultPremium;
            $monthlyDeduction = $record && (float) $record->monthly_deduction > 0
                ? (float) $record->monthly_deduction
                : round($premium / $installments, 2);

            $monthly = array_fill(1, 12, 0.0);
            foreach ($payments->get($d->id, collect()) as $p) {
                $monthly[(int) $p->month] = (float) $p->amount;
            }
            $total = round(array_sum($monthly), 2);

            return [
                'dependent_id'      => $d->id,
                'user_id'           => $d->user_id,
                'member'            => $this->displayName($members[$d->user_id]),
                'dependent'         => $this->dependentName($d),
                'relationship'      => $d->relationship,
                'premium'           => round($premium, 2),
                'monthly_deduction' => $monthlyDeduction,
                'has_record'        => (bool) $record,
                'months'            => $monthly,
                'total'             => $total,
                'balance'           => round($premium - $total, 2),
            ];
        });

        $monthTotals = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthTotals[$m] = round($rows->sum(fn ($r) => $r['months'][$m]), 2);
        }

        return [
            'rows'   => $rows->all(),
            'totals' => [
                'premium'           => round($rows->sum('premium'), 2),
                'monthly_deduction' => round($rows->sum('monthly_deduction'), 2),
                'months'            => $monthTotals,
                'total'             => round($rows->sum('total'), 2),
                'balance'           => round($rows->sum('balance'), 2),
            ],
            'default_premium' => $defaultPremium,
            'installments'    => $installments,
        ];
    }

    public function defaultPremium(): float
    {
        return (float) Configuration::getDecimal('dependent_premium_amount', 2500);
    }

    public function installments(): int
    {
        return max(1, (int) Configuration::getValue('dependent_premium_installments', '10'));
    }

    // ── 3. Dependents & Beneficiaries ─────────────────────────────

    public function dependents(): array
    {
        $members = $this->members()->load('dependents');

        return $members->values()->map(function (User $u, int $i) {
            $deps = $u->dependents->sortBy('id');
            // A beneficiary-only entry (is_beneficiary, no coverage) is not a dependent.
            $dependents = $deps->filter(fn ($d) => !$d->is_beneficiary || $d->coverage_type)->values();
            $beneficiaries = $deps->filter(fn ($d) => $d->is_beneficiary)->values();

            $lastUpdated = $deps->max('updated_at');

            return [
                'no'            => $i + 1,
                'user_id'       => $u->id,
                'name'          => $this->displayName($u),
                'birth_date'    => $u->birth_date?->toDateString(),
                'address'       => $u->address,
                'date_started'  => $u->contract_start?->toDateString(),
                'dependents'    => $dependents->map(fn ($d) => ['name' => $this->dependentName($d), 'relationship' => $d->relationship])->all(),
                'beneficiaries' => $beneficiaries->map(fn ($d) => ['name' => $this->dependentName($d), 'relationship' => $d->relationship])->all(),
                'updated'       => $lastUpdated ? Carbon::parse($lastUpdated)->toDateString() : null,
            ];
        })->all();
    }

    // ── Helpers ───────────────────────────────────────────────────

    /** @return Collection<int, User> */
    private function members(): Collection
    {
        return User::where('employment_type', self::MEMBER_TYPE)
            ->where('role', '!=', 'admin')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    private function displayName(User $u): string
    {
        $middle = $u->middle_name ? ' ' . mb_substr($u->middle_name, 0, 1) . '.' : '';
        $suffix = $u->suffix ? ' ' . $u->suffix : '';

        return trim("{$u->last_name}, {$u->first_name}{$middle}{$suffix}", ', ');
    }

    private function dependentName(Dependent $d): string
    {
        return trim(preg_replace('/\s+/', ' ', "{$d->first_name} {$d->middle_name} {$d->last_name}"));
    }
}
