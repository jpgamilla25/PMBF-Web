<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareCapital extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'year',
        'month',
        'remarks',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
