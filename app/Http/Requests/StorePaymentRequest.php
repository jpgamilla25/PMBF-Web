<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'or_number' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:cash,check,bank_transfer,payroll_deduction'],
            'payment_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'image', 'max:5120'], // 5MB max
        ];
    }
}
