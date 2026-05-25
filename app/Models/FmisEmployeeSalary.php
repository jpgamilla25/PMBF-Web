<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FmisEmployeeSalary extends Model
{
    protected $connection = 'fmis';
    protected $table = 'employee_salaries';

    protected $fillable = [
        'employee_id',
        'salary_grade',
        'step',
        'monthly_salary',
        'base_pay',
        'allowances',
        'deductions',
        'net_take_home',
        'effective_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'monthly_salary' => 'decimal:2',
            'base_pay' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_take_home' => 'decimal:2',
            'step' => 'integer',
        ];
    }
}
