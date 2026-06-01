<?php

namespace App\Models;

use Carbon\Carbon;

/**
 * Read-only DTO hydrated from the PhilRice HRIS API response.
 * Not an Eloquent model — there is no local HRIS table.
 */
class HrisEmployee
{
    public ?string $employee_id;
    public ?string $first_name;
    public ?string $middle_name;
    public ?string $last_name;
    public ?string $suffix;
    public ?string $email;
    public ?string $mobile;
    public ?string $employment_type;
    public ?string $position;
    public ?string $department;
    public ?float $base_pay;
    public ?float $take_home_pay;
    public ?Carbon $contract_start;
    public ?Carbon $contract_end;
    public ?string $status;

    public function __construct(array $attrs = [])
    {
        $this->employee_id     = $attrs['employee_id']     ?? null;
        $this->first_name      = $attrs['first_name']      ?? null;
        $this->middle_name     = $attrs['middle_name']     ?? null;
        $this->last_name       = $attrs['last_name']       ?? null;
        $this->suffix          = $attrs['suffix']          ?? null;
        $this->email           = $attrs['email']           ?? null;
        $this->mobile          = $attrs['mobile']          ?? null;
        $this->employment_type = $attrs['employment_type'] ?? null;
        $this->position        = $attrs['position']        ?? null;
        $this->department      = $attrs['department']      ?? null;
        $this->base_pay        = isset($attrs['base_pay']) ? (float) $attrs['base_pay'] : null;
        $this->take_home_pay   = isset($attrs['take_home_pay']) ? (float) $attrs['take_home_pay'] : null;
        $this->contract_start  = !empty($attrs['contract_start']) ? Carbon::parse($attrs['contract_start']) : null;
        $this->contract_end    = !empty($attrs['contract_end'])   ? Carbon::parse($attrs['contract_end'])   : null;
        $this->status          = $attrs['status']          ?? null;
    }
}
