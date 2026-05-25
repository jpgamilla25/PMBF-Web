<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasApprovalWorkflow;
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

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute(): float
    {
        $totalWithInterest = $this->monthly_amortization * $this->term_months;
        return $totalWithInterest - $this->total_paid;
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
