<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin-entered PMBF Employee — see App\Services\PmbfEmployeeService.
 *
 * No employee_id field: it is issued by the service, not supplied by the admin.
 * Route middleware already restricts this to admins.
 */
class StorePmbfEmployeeRequest extends FormRequest
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
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            // The email is the only way a PMBF Employee can sign in — the login
            // OTP goes there — so it has to be both present and unclaimed.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'mobile' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'pending', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email address already belongs to another member.',
        ];
    }
}
