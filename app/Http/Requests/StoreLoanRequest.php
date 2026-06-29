<?php

namespace App\Http\Requests;

use App\Models\Configuration;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'loan_type' => ['required', 'string', 'in:Salary Loan,Consolidated,Multi-Purpose,Emergency,Hospitalization,Temporary'],
            'amount' => ['required', 'numeric', 'min:' . Configuration::getDecimal('min_loan_amount', 1000)],
            'purpose' => ['required', 'string', 'max:500'],
            'term_months' => ['required', 'integer', 'min:1', 'max:' . Configuration::getValue('max_loan_term_months', 60)],
            'co_maker_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        // SC members: co-maker required if config says so.
        // Employment type comes from HRIS (with local fallback) to match the loan flow.
        $employeeId = $this->user()?->employee_id;
        $employmentType = $employeeId
            ? app(\App\Services\FmisService::class)->getEmploymentType($employeeId)
            : null;
        $isSC = ($employmentType ?? $this->user()?->employment_type) === 'Contract of Service';

        if ($isSC && Configuration::getBool('sc_requires_co_maker', true)) {
            $rules['co_maker_id'] = ['required', 'integer', 'exists:users,id'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'co_maker_id.required' => 'A Permanent employee co-maker is required for SC loans.',
            'amount.min' => 'Minimum loan amount is ₱' . number_format(Configuration::getDecimal('min_loan_amount', 1000), 0) . '.',
        ];
    }
}
