<?php

namespace App\Http\Requests;

use App\Models\Configuration;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // Validated against the types this member may actually apply for,
            // not a static list — that also stops a Permanent member submitting
            // an SC-only type and vice versa, and can't drift when a new type
            // is added to LoanService.
            'loan_type' => ['required', 'string', Rule::in($this->allowedLoanTypes())],
            'amount' => ['required', 'numeric', 'min:' . Configuration::getDecimal('min_loan_amount', 1000)],
            'purpose' => ['required', 'string', 'max:500'],
            'term_months' => ['required', 'numeric', 'min:0.5', 'max:' . Configuration::getValue('max_loan_term_months', 60)],
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

    /**
     * Loan types this member may apply for. Falls back to every valid enum
     * value if the member can't be resolved, so a transient HRIS/FMIS failure
     * surfaces as a business-rule error downstream rather than a confusing
     * "invalid loan type" on a type the user was just offered.
     */
    private function allowedLoanTypes(): array
    {
        $user = $this->user();

        if (!$user) {
            return Loan::TYPES;
        }

        try {
            $available = array_keys(app(LoanService::class)->getAvailableLoanTypes($user));
        } catch (\Throwable) {
            return Loan::TYPES;
        }

        return $available ?: Loan::TYPES;
    }

    public function messages(): array
    {
        return [
            'loan_type.in' => 'That loan type is not available for your employment type.',
            'co_maker_id.required' => 'A Permanent employee co-maker is required for SC loans.',
            'amount.min' => 'Minimum loan amount is ₱' . number_format(Configuration::getDecimal('min_loan_amount', 1000), 0) . '.',
        ];
    }
}
