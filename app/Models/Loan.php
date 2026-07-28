<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasApprovalWorkflow;

    /**
     * Every value the loan_type column accepts.
     *
     * Single source of truth — keep in step with the enum in
     * database/migrations/2026_07_21_000002_add_regular_loan_type.php.
     * Which of these a given member may actually apply for is decided by
     * LoanService::getAvailableLoanTypes(), not by this list.
     */
    public const TYPES = [
        'Salary Loan',
        // 'Regular' is retired (duplicate of Consolidated). Kept in the DB
        // enum for any historical rows, but no longer offered or accepted.
        'Consolidated',
        'Multi-Purpose',
        'Emergency',
        'Hospitalization',
        'Temporary',
    ];

    protected $fillable = [
        'user_id',
        'loan_type',
        'amount',
        'purpose',
        'interest_rate',
        'interest_method',
        'term_months',
        'start_date',
        'renewed_from_loan_id',
        'monthly_amortization',
        'total_payable',
        'co_maker_id',
        'co_maker_id_2',
        'co_maker_token',
        'co_maker_token_2',
        'co_maker_status',
        'co_maker_status_2',
        'co_maker_acted_at',
        'co_maker_acted_at_2',
        'requires_admin_approval',
        'status',
        'remarks',
        'applied_at',
        'approved_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            // 4 dp so a per-annum rate (8% ÷ 12 = 0.6667%/mo) is not rounded
            // back to 0.67 on read, which would desync the schedule from the
            // stored monthly amortization.
            'interest_rate' => 'decimal:4',
            'monthly_amortization' => 'decimal:2',
            // Fractional to allow half-month terms (e.g. 5.5).
            'term_months' => 'decimal:2',
            // NB: total_payable is intentionally NOT cast — the accessor below
            // reads the raw stored value and falls back to the flat formula.
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'start_date' => 'date',
            'co_maker_acted_at' => 'datetime',
            'co_maker_acted_at_2' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function coMaker()
    {
        return $this->belongsTo(User::class, 'co_maker_id');
    }

    public function coMaker2()
    {
        return $this->belongsTo(User::class, 'co_maker_id_2');
    }

    /**
     * Every named co-maker, as [slot => user_id], skipping empty slots.
     *
     * @return array<int, int>
     */
    public function coMakerIds(): array
    {
        return array_filter([1 => $this->co_maker_id, 2 => $this->co_maker_id_2]);
    }

    public function approvals()
    {
        return $this->hasMany(LoanApproval::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Human-facing transaction reference (e.g. TXN-2026-000003).
     * Derived from the application year + zero-padded id so it reads as a
     * unique reference rather than a per-member loan sequence number.
     */
    public function getReferenceNoAttribute(): string
    {
        $date = $this->applied_at ?? $this->created_at;
        $year = $date ? $date->format('Y') : date('Y');

        return sprintf('TXN-%s-%06d', $year, $this->id);
    }

    /**
     * Prefers an eager-loaded sum (withSum('payments', 'amount')) so a list
     * doesn't run one aggregate query per row, and falls back to querying for
     * a single loan that wasn't loaded that way.
     */
    public function getTotalPaidAttribute(): float
    {
        if (array_key_exists('payments_sum_amount', $this->attributes)) {
            return (float) ($this->attributes['payments_sum_amount'] ?? 0);
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    /**
     * Total payable over the life of the loan.
     *
     * New loans store the exact figure at creation (the only reliable source
     * for a diminishing-balance loan once rounding is applied). Legacy loans
     * with no stored value fall back to the flat formula — principal + flat
     * interest — computed from the principal, not monthly × term, to avoid
     * re-introducing per-month rounding.
     */
    public function getTotalPayableAttribute(): float
    {
        $stored = $this->attributes['total_payable'] ?? null;

        if ($stored !== null) {
            return (float) $stored;
        }

        $interest = (float) $this->amount * ((float) $this->interest_rate / 100) * (float) $this->term_months;

        return round((float) $this->amount + $interest, 2);
    }

    public function getRemainingBalanceAttribute(): float
    {
        // A renewed loan is closed — its balance was rolled into the renewal,
        // not paid off — so it shows zero owing rather than the old figure.
        if (in_array($this->status, ['renewed', 'completed'], true)) {
            return 0.0;
        }

        return $this->total_payable - $this->total_paid;
    }

    public function getNextApprovalLevelAttribute(): ?string
    {
        if ($this->status === 'co_maker_pending') return 'co_maker';
        if ($this->status === 'admin_pending') return 'admin';
        if ($this->status === 'pending') return 'receiver';
        if ($this->status === 'receiver_approved') return 'loan_committee';
        if ($this->status === 'committee_approved') return 'chairperson';
        if ($this->status === 'temporary_submission') return 'board';
        return null;
    }
}
