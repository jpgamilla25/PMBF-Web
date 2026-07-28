<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmploymentStint extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'is_current',
        'hris_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date'     => 'date',
            'end_date'       => 'date',
            'is_current'     => 'boolean',
            'hris_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
}
