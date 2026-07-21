<?php

namespace App\Http\Resources;

use App\Services\HrisService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Whether to overlay live HRIS values on top of the local snapshot.
     *
     * Off by default. This used to be unconditional, which meant one HTTP
     * call to the HRIS API per serialized user — a 20-row members table cost
     * 20 sequential API calls and took tens of seconds. The users table
     * already carries a snapshot of these fields, so lists read from that and
     * only single-record endpoints ask HRIS for live values.
     */
    private bool $withHris = false;

    /** Opt this resource in to a live HRIS lookup. */
    public function withHris(bool $enabled = true): static
    {
        $this->withHris = $enabled;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hris = $this->withHris && $this->employee_id
            ? app(HrisService::class)->findByEmployeeId($this->employee_id)
            : null;

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
            'employment_type' => $hris?->employment_type ?? $this->employment_type,
            'position' => $hris?->position ?? $this->position,
            'department' => $hris?->department ?? $this->department,
            'base_pay' => $hris?->base_pay ?? $this->base_pay,
            'take_home_pay' => $hris?->take_home_pay ?? $this->take_home_pay,
            'role' => $this->role,
            'status' => $this->status,
            'has_pin' => (bool) $this->pin,
            'hris_synced_at' => $this->hris_synced_at?->toIso8601String(),
            'contract_start' => $hris?->contract_start?->toDateString() ?? $this->contract_start?->toDateString(),
            'contract_end' => $hris?->contract_end?->toDateString() ?? $this->contract_end?->toDateString(),
        ];
    }
}
