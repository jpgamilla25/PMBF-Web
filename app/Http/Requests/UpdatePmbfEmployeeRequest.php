<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrections to an admin-entered PMBF Employee.
 *
 * Only the fields an admin typed in the first place are editable. employee_id
 * stays server-owned and role is assigned through User Type Management, so
 * neither is accepted here.
 */
class UpdatePmbfEmployeeRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            // Still the sign-in identifier, so it stays unique — but the
            // member's own current address must not collide with itself.
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'mobile' => ['nullable', 'string', 'max:30'],
            'position' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'pending', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email address already belongs to another member.',
        ];
    }
}
