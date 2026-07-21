<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkApprovalActionRequest extends FormRequest
{
    /**
     * Maximum number of loans that may be acted on in a single batch.
     */
    public const MAX_BATCH = 50;

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
            'loan_ids' => ['required', 'array', 'min:1', 'max:' . self::MAX_BATCH],
            'loan_ids.*' => ['integer', 'distinct'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'loan_ids.required' => 'Select at least one loan.',
            'loan_ids.max' => 'You can only process up to ' . self::MAX_BATCH . ' loans at a time.',
        ];
    }
}
