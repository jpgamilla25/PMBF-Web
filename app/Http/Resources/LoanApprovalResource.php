<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApprovalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'approver_id' => $this->approver_id,
            'level' => $this->level,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'acted_at' => $this->acted_at?->toIso8601String(),
            'approver' => new UserResource($this->whenLoaded('approver')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
