<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanExemptionRequest extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'loan_type',
        'current_value',
        'required_value',
        'requested_value',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_remarks',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:2',
            'required_value' => 'decimal:2',
            'requested_value' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Determine if this exemption is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'approved'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Scope to only active (approved and not expired) exemptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to only pending exemptions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
