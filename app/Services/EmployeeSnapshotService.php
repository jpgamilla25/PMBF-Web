<?php

namespace App\Services;

use App\Models\HrisEmployee;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Keeps the copy of employment details held on the users table in step with
 * HRIS.
 *
 * List views read that snapshot instead of calling the HRIS API once per row,
 * so without a refresh path a pay adjustment would never reach them. Loan
 * decisions always read HRIS live and never depend on this.
 *
 * Shared by the nightly hris:sync-employees command and the "Sync my data"
 * action so both apply exactly the same rules.
 */
class EmployeeSnapshotService
{
    public function __construct(private readonly HrisService $hris) {}

    /** Fields mirrored from HRIS onto the users table. */
    public const SYNCED = [
        'employment_type', 'position', 'department',
        'base_pay', 'take_home_pay', 'contract_start', 'contract_end',
    ];

    /**
     * Refresh one member's snapshot.
     *
     * @return array{available: bool, found: bool, changed: bool, changes: array<string, mixed>}
     */
    public function refresh(User $user): array
    {
        $employee = $this->lookup($user);

        if (!$employee) {
            // Could be an outage or a genuinely unknown employee. Either way
            // the existing snapshot is the best data we have — never wipe it.
            return ['available' => false, 'found' => false, 'changed' => false, 'changes' => []];
        }

        $changes = $this->diff($user, $employee);

        // Stamp the sync even when nothing changed, so "last synced" reflects
        // when we last confirmed the data rather than when it last moved.
        $user->forceFill($changes + ['hris_synced_at' => now()])->save();

        return [
            'available' => true,
            'found' => true,
            'changed' => !empty($changes),
            'changes' => $changes,
        ];
    }

    /**
     * What refresh() would change, without writing anything.
     *
     * @return array{available: bool, changes: array<string, mixed>}
     */
    public function refreshPreview(User $user): array
    {
        $employee = $this->lookup($user);

        return $employee
            ? ['available' => true, 'changes' => $this->diff($user, $employee)]
            : ['available' => false, 'changes' => []];
    }

    /**
     * The HRIS record behind this member, if there is one to find.
     *
     * PMBF Employees are created by an admin and carry a locally-issued member
     * ID, so PhilRice HRIS has nothing to return for them — skip the call
     * rather than pay the api-center timeout once per member per night.
     */
    private function lookup(User $user): ?HrisEmployee
    {
        if (!$user->employee_id || !$user->isHrisBacked()) {
            return null;
        }

        return $this->hris->findByEmployeeId($user->employee_id);
    }

    /**
     * Only fields that actually differ, so an unchanged member isn't rewritten.
     *
     * @return array<string, mixed>
     */
    public function diff(User $user, HrisEmployee $employee): array
    {
        $changes = [];

        foreach (self::SYNCED as $field) {
            $incoming = $employee->{$field} ?? null;

            // HRIS didn't supply it — keep whatever we already have rather
            // than blanking a good value.
            if ($incoming === null || $incoming === '') {
                continue;
            }

            if ($this->normalise($user->{$field}) !== $this->normalise($incoming)) {
                $changes[$field] = $incoming instanceof CarbonInterface
                    ? $incoming->toDateString()
                    : $incoming;
            }
        }

        return $changes;
    }

    private function normalise(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return is_numeric($value)
            ? number_format((float) $value, 2, '.', '')
            : trim((string) $value);
    }
}
