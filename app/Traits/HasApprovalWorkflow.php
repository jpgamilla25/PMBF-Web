<?php

namespace App\Traits;

use App\Models\LoanApproval;
use App\Models\User;

trait HasApprovalWorkflow
{
    /**
     * Determine the next approval level based on current loan status.
     */
    public function getNextApprovalLevel(): ?string
    {
        return match ($this->status) {
            'co_maker_pending' => 'co_maker',
            'admin_pending' => 'admin',
            'pending' => 'receiver',
            'receiver_approved' => 'loan_committee',
            'committee_approved' => 'chairperson',
            'chairperson_approved' => 'release',
            default => null,
        };
    }

    /**
     * Check if a given user can approve this loan at the current stage.
     */
    public function canBeApprovedBy(User $user): bool
    {
        $nextLevel = $this->getNextApprovalLevel();

        if (is_null($nextLevel)) {
            return false;
        }

        $roleMap = [
            'admin' => 'admin',
            'receiver' => 'receiver',
            'loan_committee' => 'loan_committee',
            'chairperson' => 'chairperson',
        ];

        $requiredRole = $roleMap[$nextLevel] ?? null;

        if (is_null($requiredRole)) {
            return false;
        }

        return $user->role === $requiredRole;
    }

    /**
     * Advance the loan approval to the next stage.
     */
    public function advanceApproval(User $approver, string $status, ?string $remarks = null): LoanApproval
    {
        $level = $this->getNextApprovalLevel();

        $approval = LoanApproval::create([
            'loan_id' => $this->id,
            'approver_id' => $approver->id,
            'level' => $level,
            'status' => $status,
            'remarks' => $remarks,
            'acted_at' => now(),
        ]);

        // Update the loan status based on action
        if ($status === 'approved') {
            $statusMap = [
                'admin' => 'pending',          // admin approval → moves to receiver queue
                'receiver' => 'receiver_approved',
                'loan_committee' => 'committee_approved',
                'chairperson' => 'chairperson_approved',
            ];

            $this->update([
                'status' => $statusMap[$level] ?? $this->status,
                'approved_at' => $level === 'chairperson' ? now() : $this->approved_at,
            ]);
        } elseif ($status === 'disapproved') {
            $this->update([
                'status' => 'disapproved',
                'remarks' => $remarks,
            ]);
        } elseif ($status === 'released') {
            $this->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
        }

        return $approval;
    }

    /**
     * Get the approval progress with steps and their statuses.
     */
    public function getApprovalProgress(): array
    {
        $steps = ['receiver', 'loan_committee', 'chairperson'];
        $approvals = $this->approvals()->get()->keyBy('level');

        $progress = [];

        // Prepend co_maker step if this loan has a co-maker
        if ($this->co_maker_id) {
            $coMakerStatus = match ($this->co_maker_status) {
                'approved' => 'approved',
                'declined' => 'disapproved',
                default => $this->status === 'co_maker_pending' ? 'pending' : 'approved',
            };
            $progress[] = [
                'level' => 'co_maker',
                'label' => 'Co-Maker Consent',
                'status' => $coMakerStatus,
                'approver' => $this->coMaker?->full_name ?? null,
                'remarks' => null,
                'acted_at' => $this->co_maker_acted_at?->toIso8601String(),
            ];
        }

        // Add admin approval step if loan exceeds max amount
        if ($this->requires_admin_approval) {
            $adminApproval = $approvals->get('admin');
            $adminStatus = $adminApproval?->status ?? ($this->status === 'admin_pending' ? 'pending' : 'approved');
            $progress[] = [
                'level' => 'admin',
                'label' => 'Admin Approval (Above Max)',
                'status' => $adminStatus,
                'approver' => $adminApproval?->approver?->full_name,
                'remarks' => $adminApproval?->remarks,
                'acted_at' => $adminApproval?->acted_at?->toIso8601String(),
            ];
        }

        foreach ($steps as $step) {
            $approval = $approvals->get($step);

            $progress[] = [
                'level' => $step,
                'label' => match ($step) {
                    'receiver' => 'Receiver',
                    'loan_committee' => 'Loan Committee',
                    'chairperson' => 'Chairperson',
                    default => ucfirst($step),
                },
                'status' => $approval?->status ?? 'pending',
                'approver' => $approval?->approver?->full_name,
                'remarks' => $approval?->remarks,
                'acted_at' => $approval?->acted_at?->toIso8601String(),
            ];
        }

        return $progress;
    }
}
