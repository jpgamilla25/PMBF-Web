<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrisEmployee extends Model
{
    protected $connection = 'hris';
    protected $table = 'employees';

    protected $fillable = [
        'employee_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'mobile',
        'employment_type',
        'position',
        'department',
        'base_pay',
        'take_home_pay',
        'contract_start',
        'contract_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'base_pay' => 'decimal:2',
            'take_home_pay' => 'decimal:2',
            'contract_start' => 'date',
            'contract_end' => 'date',
        ];
    }
}
