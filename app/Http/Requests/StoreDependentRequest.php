<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDependentRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'relationship' => ['required', 'string', 'in:Spouse,Child,Parent,Sibling,Other'],
            'birth_date' => ['required', 'date', 'before:today'],
            'coverage_type' => ['nullable', 'string', 'in:Dental,Hospitalization,Both'],
        ];
    }
}
