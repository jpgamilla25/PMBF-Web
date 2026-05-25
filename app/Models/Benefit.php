<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Benefit extends Model
{
    protected $fillable = [
        'user_id',
        'benefit_type',
        'description',
        'amount',
        'share_capital',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'share_capital' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
