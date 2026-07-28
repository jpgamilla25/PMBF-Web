<?php

namespace App\Console\Commands;

use App\Models\EmploymentStint;
use App\Models\ShareCapital;
use App\Models\User;
use App\Services\HrisService;
use Illuminate\Console\Command;

/**
 * One-shot fix-up: rewrite `share_capitals.type` on any row whose current tag
 * disagrees with the employment stint that covered its (year, month).
 *
 * Historical rows were tagged from the current-employment snapshot before
 * stint history existed, so a member promoted from COS → Permanent had ALL
 * their pre-promotion rows silently tagged as 'share'. This command corrects
 * them to 'premium'.
 *
 * Run after hris:sync-employment-stints has populated the stint table.
 */
class RetagSharesFromStints extends Command
{
    protected $signature = 'shares:retag-from-stints
        {--employee= : Restrict to a single employee_id}
        {--dry-run : Print the corrections without writing them}';

    protected $description = 'Correct share_capitals.type based on the employment_stints history.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('employee');

        $userQuery = User::query()->whereNotNull('employee_id');
        if ($only) {
            $userQuery->where('employee_id', $only);
        }

        $userIdToEmpId = $userQuery->pluck('employee_id', 'id')->all();
        if ($userIdToEmpId === []) {
            $this->warn('No matching users.');
            return self::SUCCESS;
        }

        $stintsByEmployee = EmploymentStint::whereIn('employee_id', array_values($userIdToEmpId))
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');

        $totals = ['checked' => 0, 'corrected' => 0, 'skipped_no_stints' => 0, 'no_stint_covers' => 0];

        $rows = ShareCapital::whereIn('user_id', array_keys($userIdToEmpId))
            ->orderBy('user_id')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        foreach ($rows as $row) {
            $totals['checked']++;
            $empId = $userIdToEmpId[$row->user_id] ?? null;
            $stints = $empId ? $stintsByEmployee->get($empId) : null;

            if (!$stints || $stints->isEmpty()) {
                $totals['skipped_no_stints']++;
                continue;
            }

            $expected = HrisService::stintTypeAt($stints, (int) $row->year, (int) $row->month);
            if ($expected === null) {
                $totals['no_stint_covers']++;
                continue;
            }

            if ($expected === $row->type) {
                continue;
            }

            $this->line(sprintf(
                '  %s row #%d (%d-%02d): %s → %s',
                $empId,
                $row->id,
                $row->year,
                $row->month,
                $row->type,
                $expected
            ));

            if (!$dryRun) {
                $row->update(['type' => $expected]);
            }
            $totals['corrected']++;
        }

        $this->info(sprintf(
            'Done — %d checked, %d corrected, %d rows for members with no stint data, %d rows whose (year,month) is outside any stint%s',
            $totals['checked'],
            $totals['corrected'],
            $totals['skipped_no_stints'],
            $totals['no_stint_covers'],
            $dryRun ? ' [dry-run — no writes]' : ''
        ));

        return self::SUCCESS;
    }
}
