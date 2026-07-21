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
        'Regular',
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
        'term_months',
        'monthly_amortization',
        'co_maker_id',
        'co_maker_token',
        'co_maker_status',
        'co_maker_acted_at',
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
            'interest_rate' => 'decimal:2',
            'monthly_amortization' => 'decimal:2',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'co_maker_acted_at' => 'datetime',
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

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    /**
     * Exact total payable = principal + flat interest (rate% per month × term).
     * Computed from the principal, NOT from monthly_amortization × term, which
     * would re-introduce the per-month rounding (e.g. 88,333.33 × 6 = 529,999.98
     * instead of the correct 530,000.00).
     */
    public function getTotalPayableAttribute(): float
    {
        $interest = (float) $this->amount * ((float) $this->interest_rate / 100) * (int) $this->term_months;

        return round((float) $this->amount + $interest, 2);
    }

    public function getRemainingBalanceAttribute(): float
    {
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
