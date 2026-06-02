<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

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
        'role',
        'password',
        'pin',
        'device_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'base_pay' => 'decimal:2',
            'take_home_pay' => 'decimal:2',
            'contract_start' => 'date',
            'contract_end' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $name = "{$this->first_name} {$this->last_name}";
        if ($this->suffix) {
            $name .= " {$this->suffix}";
        }
        return $name;
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function coMakerLoans()
    {
        return $this->hasMany(Loan::class, 'co_maker_id');
    }

    public function dependents()
    {
        return $this->hasMany(Dependent::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function benefits()
    {
        return $this->hasMany(Benefit::class);
    }

    public function approvals()
    {
        return $this->hasMany(LoanApproval::class, 'approver_id');
    }

    public function isSC(): bool
    {
        return $this->employment_type === 'Contract of Service';
    }

    public function isPermanent(): bool
    {
        return $this->employment_type === 'Permanent';
    }

    public function isNonMember(): bool
    {
        return $this->employment_type === 'Non-Member';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isReceiver(): bool
    {
        return $this->role === 'receiver';
    }

    public function isLoanCommittee(): bool
    {
        return $this->role === 'loan_committee';
    }

    public function isChairperson(): bool
    {
        return $this->role === 'chairperson';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'receiver', 'loan_committee', 'chairperson']);
    }
}
