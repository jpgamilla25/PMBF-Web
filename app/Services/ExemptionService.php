<?php

namespace App\Services;

use App\Mail\ExemptionRequestMail;
use App\Models\Configuration;
use App\Models\LoanExemptionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ExemptionService
{
    /**
     * Which role reviews a given exemption type.
     *
     * Below-minimum-take-home requests are a policy call, so they go to the
     * Loan Committee rather than the administrator. Amount and term
     * exemptions stay with the admin.
     */
    public static function reviewerRole(string $type): string
    {
        return $type === 'below_minimum_pay' ? 'loan_committee' : 'admin';
    }

    /** Exemption types a given role is responsible for reviewing. */
    public static function typesForRole(string $role): array
    {
        return match ($role) {
            'loan_committee' => ['below_minimum_pay'],
            'admin' => ['exceed_max_amount', 'extend_term'],
            default => [],
        };
    }

    /**
     * Create an exemption request. Emails the role that reviews this type.
     */
    public function createRequest(User $user, array $data): LoanExemptionRequest
    {
        $request = LoanExemptionRequest::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'loan_type' => $data['loan_type'],
            'current_value' => $data['current_value'],
            'required_value' => $data['required_value'],
            'requested_value' => $data['requested_value'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        $request->load('user');

        // Notify the reviewing role (committee for below-min-pay, else admin).
        $reviewerEmails = User::where('role', self::reviewerRole($data['type']))
            ->where('status', 'active')
            ->pluck('email')
            ->toArray();

        if (!empty($reviewerEmails)) {
            Mail::to($reviewerEmails)->send(new ExemptionRequestMail($request));
        }

        return $request;
    }

    /**
     * Check if user has an active approved exemption for a specific type.
     */
    public function hasActiveExemption(User $user, string $type, ?string $loanType = null): bool
    {
        $query = LoanExemptionRequest::where('user_id', $user->id)
            ->where('type', $type)
            ->active();

        if ($loanType !== null) {
            $query->where('loan_type', $loanType);
        }

        return $query->exists();
    }

    /**
     * Get the approved exemption (returns the record for amount reference).
     */
    public function getActiveExemption(User $user, string $type, ?string $loanType = null): ?LoanExemptionRequest
    {
        $query = LoanExemptionRequest::where('user_id', $user->id)
            ->where('type', $type)
            ->active();

        if ($loanType !== null) {
            $query->where('loan_type', $loanType);
        }

        return $query->latest()->first();
    }

    /**
     * Approve an exemption request. Sets expires_at to 90 days from now.
     */
    public function approve(LoanExemptionRequest $request, User $reviewer, ?string $remarks = null): void
    {
        $request->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_remarks' => $remarks,
            'expires_at' => now()->addDays((int) Configuration::getValue('exemption_approval_validity_days', 90)),
        ]);
    }

    /**
     * Reject an exemption request.
     */
    public function reject(LoanExemptionRequest $request, User $reviewer, string $remarks): void
    {
        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'reviewer_remarks' => $remarks,
        ]);
    }
}
