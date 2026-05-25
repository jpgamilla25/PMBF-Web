<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'suffix' => $this->suffix,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'employment_type' => $this->employment_type,
            'position' => $this->position,
            'department' => $this->department,
            'base_pay' => $this->base_pay,
            'take_home_pay' => $this->take_home_pay,
            'role' => $this->role,
            'status' => $this->status,
            'contract_start' => $this->contract_start?->toDateString(),
            'contract_end' => $this->contract_end?->toDateString(),
        ];
    }
}
