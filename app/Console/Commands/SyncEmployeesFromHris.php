<?php

namespace App\Console\Commands;

use App\Models\HrisEmployee;
use App\Models\User;
use App\Services\HrisService;
use Illuminate\Console\Command;

/**
 * Refresh the local snapshot of employee details held on the users table.
 *
 * List views read that snapshot rather than calling the HRIS API per row, so
 * without this a pay adjustment made in HRIS would never appear in the admin
 * lists. Loan eligibility already reads HRIS live, so this is about keeping
 * what is displayed in step with what is decided.
 */
class SyncEmployeesFromHris extends Command
{
    protected $signature = 'hris:sync-employees
        {--employee= : Sync a single employee ID}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Refresh employment type, pay and contract dates on the users table from the HRIS api-center.';

    /** Fields mirrored from HRIS onto the users table. */
    private const SYNCED = [
        'employment_type', 'position', 'department',
        'base_pay', 'take_home_pay', 'contract_start', 'contract_end',
    ];

    public function handle(HrisService $hris): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()->whereNotNull('employee_id');

        if ($employeeId = $this->option('employee')) {
            $query->where('employee_id', $employeeId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No users to sync.');

            return self::SUCCESS;
        }

        $updated = $missing = $unchanged = 0;

        foreach ($users as $user) {
            $employee = $hris->findByEmployeeId($user->employee_id);

            if (!$employee) {
                $missing++;
                $this->line("  <fg=yellow>miss</> {$user->employee_id} — not found in HRIS, snapshot left as-is");
                continue;
            }

            $changes = $this->diff($user, $employee);

            if (empty($changes)) {
                $unchanged++;
                continue;
            }

            $this->line("  <fg=green>sync</> {$user->employee_id} — " . $this->describe($changes));

            if (!$dryRun) {
                $user->forceFill($changes)->save();
            }

            $updated++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d updated, %d unchanged, %d not in HRIS (of %d).',
            $dryRun ? 'Would have applied:' : 'Done:',
            $updated,
            $unchanged,
            $missing,
            $users->count()
        ));

        return self::SUCCESS;
    }

    /**
     * Only fields that actually differ, so an unchanged employee is not
     * rewritten (and updated_at stays meaningful).
     *
     * @return array<string, mixed>
     */
    private function diff(User $user, HrisEmployee $employee): array
    {
        $changes = [];

        foreach (self::SYNCED as $field) {
            $incoming = $employee->{$field} ?? null;

            if ($incoming === null || $incoming === '') {
                continue; // HRIS did not supply it — keep what we have.
            }

            if ($this->normalise($user->{$field}) !== $this->normalise($incoming)) {
                $changes[$field] = $incoming instanceof \Carbon\Carbon
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

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toDateString();
        }

        return is_numeric($value)
            ? number_format((float) $value, 2, '.', '')
            : trim((string) $value);
    }

    /** @param array<string, mixed> $changes */
    private function describe(array $changes): string
    {
        return implode(', ', array_map(
            fn ($field, $value) => "{$field}=" . ($value instanceof \Carbon\Carbon ? $value->toDateString() : $value),
            array_keys($changes),
            $changes
        ));
    }
}
