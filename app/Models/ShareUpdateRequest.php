<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareUpdateRequest extends Model
{
    protected $fillable = [
        'user_id',
        'current_amount',
        'requested_amount',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_remarks',
    ];

    protected function casts(): array
    {
        return [
            'current_amount' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
