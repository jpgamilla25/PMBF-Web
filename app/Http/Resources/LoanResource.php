<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'user_id' => $this->user_id,
            'loan_type' => $this->loan_type,
            'amount' => $this->amount,
            'purpose' => $this->purpose,
            'interest_rate' => $this->interest_rate,
            'term_months' => $this->term_months,
            'monthly_amortization' => $this->monthly_amortization,
            'total_payable' => $this->total_payable,
            'co_maker_id' => $this->co_maker_id,
            'co_maker_status' => $this->co_maker_status,
            'co_maker_acted_at' => $this->co_maker_acted_at?->toIso8601String(),
            'status' => $this->status,
            'remarks' => $this->remarks,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'user' => new UserResource($this->whenLoaded('user')),
            'co_maker' => new UserResource($this->whenLoaded('coMaker')),
            'approvals' => LoanApprovalResource::collection($this->whenLoaded('approvals')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->whenCounted('payments'),
            'total_paid' => $this->when(
                $this->relationLoaded('payments'),
                fn() => $this->total_paid
            ),
            'remaining_balance' => $this->when(
                $this->relationLoaded('payments'),
                fn() => $this->remaining_balance
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Approval helpers for frontend
            'can_act' => $this->when(
                request()->user(),
                fn () => $this->canBeApprovedBy(request()->user())
            ),
            'can_release' => $this->when(
                request()->user(),
                fn () => $this->status === 'chairperson_approved' &&
                    (request()->user()->isAdmin() || request()->user()->isReceiver())
            ),
            'next_approval_level' => $this->getNextApprovalLevel(),
            'approval_progress' => $this->when(
                $this->relationLoaded('approvals'),
                fn () => $this->getApprovalProgress()
            ),
        ];
    }
}
