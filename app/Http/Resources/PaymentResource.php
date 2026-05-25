<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'recorded_by' => $this->recorded_by,
            'amount' => $this->amount,
            'or_number' => $this->or_number,
            'payment_method' => $this->payment_method,
            'payment_date' => $this->payment_date?->toDateString(),
            'remarks' => $this->remarks,
            'receipt_url' => $this->receipt_path ? asset('storage/' . $this->receipt_path) : null,
            'loan' => $this->when($this->relationLoaded('loan'), fn() => [
                'id' => $this->loan->id,
                'loan_type' => $this->loan->loan_type,
                'amount' => $this->loan->amount,
                'status' => $this->loan->status,
            ]),
            'member' => $this->when(
                $this->relationLoaded('loan') && $this->loan->relationLoaded('user'),
                fn() => [
                    'id' => $this->loan->user->id,
                    'full_name' => $this->loan->user->full_name,
                    'employee_id' => $this->loan->user->employee_id,
                    'employment_type' => $this->loan->user->employment_type,
                    'department' => $this->loan->user->department,
                ]
            ),
            'recorder' => $this->when($this->relationLoaded('recorder'), fn() => [
                'id' => $this->recorder->id,
                'full_name' => $this->recorder->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
