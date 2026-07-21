<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EmployeeSnapshotService;
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

    public function handle(EmployeeSnapshotService $snapshots): int
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

        $updated = $unavailable = $unchanged = 0;

        foreach ($users as $user) {
            // A dry run must not write, so diff directly instead of refreshing.
            if ($dryRun) {
                $employee = $snapshots->refreshPreview($user);

                if (!$employee['available']) {
                    $unavailable++;
                    $this->line("  <fg=yellow>skip</> {$user->employee_id} — no answer from HRIS, snapshot left as-is");
                    continue;
                }

                if (empty($employee['changes'])) {
                    $unchanged++;
                    continue;
                }

                $updated++;
                $this->line("  <fg=green>diff</> {$user->employee_id} — " . $this->describe($employee['changes']));
                continue;
            }

            $result = $snapshots->refresh($user);

            if (!$result['available']) {
                $unavailable++;
                $this->line("  <fg=yellow>skip</> {$user->employee_id} — no answer from HRIS, snapshot left as-is");
                continue;
            }

            if (!$result['changed']) {
                $unchanged++;
                continue;
            }

            $updated++;
            $this->line("  <fg=green>sync</> {$user->employee_id} — " . $this->describe($result['changes']));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d updated, %d unchanged, %d unavailable (of %d).',
            $dryRun ? 'Would have applied:' : 'Done:',
            $updated,
            $unchanged,
            $unavailable,
            $users->count()
        ));

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $changes */
    private function describe(array $changes): string
    {
        return implode(', ', array_map(
            fn ($field, $value) => "{$field}={$value}",
            array_keys($changes),
            $changes
        ));
    }
}
