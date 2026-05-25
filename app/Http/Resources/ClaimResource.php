<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'dependent_id' => $this->dependent_id,
            'claim_type' => $this->claim_type,
            'description' => $this->description,
            'amount' => $this->amount,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'dependent' => new DependentResource($this->whenLoaded('dependent')),
            'attachments_count' => $this->whenCounted('attachments'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
