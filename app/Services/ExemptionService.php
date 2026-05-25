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
     * Create an exemption request. Sends email notification to admin users.
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

        // Notify all admin users
        $adminEmails = User::where('role', 'admin')->pluck('email')->toArray();

        if (!empty($adminEmails)) {
            Mail::to($adminEmails)->send(new ExemptionRequestMail($request));
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
