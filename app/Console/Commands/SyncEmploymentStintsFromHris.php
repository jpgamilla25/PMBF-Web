<?php

namespace App\Console\Commands;

use App\Models\EmploymentStint;
use App\Models\User;
use App\Services\HrisService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly mirror of the HRIS employment-stint history into local
 * `employment_stints`. FMIS shares sync consults this table to decide each
 * row's type — see SyncSharesFromFmis::writeBatch().
 *
 * Deletes any local stints HRIS no longer returns for that employee (HRIS is
 * authoritative). If HRIS is unreachable for an employee, that employee's
 * local stints are left untouched — a transient outage never wipes data.
 */
class SyncEmploymentStintsFromHris extends Command
{
    protected $signature = 'hris:sync-employment-stints
        {--employee= : Restrict the sync to a single employee_id}
        {--dry-run : Report the changes without writing them}';

    protected $description = 'Pull employment-stint history from HRIS and mirror into the local employment_stints table.';

    public function handle(HrisService $hris): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('employee');

        $query = User::query()->whereNotNull('employee_id');
        if ($only) {
            $query->where('employee_id', $only);
        }

        $employees = $query->pluck('employee_id')->unique()->values();

        $totals = ['employees' => 0, 'upserted' => 0, 'removed' => 0, 'skipped' => 0];
        $now = now();

        $this->line(sprintf(
            'Syncing employment stints for %d employee(s)%s',
            $employees->count(),
            $dryRun ? ' [dry-run]' : ''
        ));

        foreach ($employees as $empId) {
            $stints = $hris->getEmployment($empId);
            if ($stints === null) {
                $this->warn("  {$empId} — HRIS unavailable, skipped");
                $totals['skipped']++;
                continue;
            }

            $totals['employees']++;

            $keepStartDates = collect($stints)->map(fn ($s) => $s['start_date']->toDateString())->all();

            if (!$dryRun) {
                DB::transaction(function () use ($empId, $stints, $keepStartDates, $now) {
                    if ($stints !== []) {
                        EmploymentStint::upsert(
                            collect($stints)->map(fn ($s) => [
                                'employee_id'    => $empId,
                                'type'           => $s['type'],
                                'start_date'     => $s['start_date']->toDateString(),
                                'end_date'       => $s['end_date']?->toDateString(),
                                'is_current'     => $s['is_current'],
                                'hris_synced_at' => $now,
                                'created_at'     => $now,
                                'updated_at'     => $now,
                            ])->all(),
                            ['employee_id', 'start_date'],
                            ['type', 'end_date', 'is_current', 'hris_synced_at', 'updated_at']
                        );
                    }

                    // Delete any local stints HRIS no longer returns. If the
                    // upstream list is empty, wipe all local stints for that
                    // employee — HRIS says none apply.
                    EmploymentStint::where('employee_id', $empId)
                        ->when($keepStartDates !== [], fn ($q) => $q->whereNotIn('start_date', $keepStartDates))
                        ->delete();
                });
            }

            $totals['upserted'] += count($stints);
        }

        $this->info(sprintf(
            'Done — %d employees synced (%d stints upserted), %d skipped (HRIS unavailable)%s',
            $totals['employees'],
            $totals['upserted'],
            $totals['skipped'],
            $dryRun ? ' [dry-run — no writes]' : ''
        ));

        return self::SUCCESS;
    }
}
