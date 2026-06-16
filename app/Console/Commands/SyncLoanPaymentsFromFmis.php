<?php

namespace App\Console\Commands;

use App\Models\FmisLoanPayment;
use App\Models\User;
use App\Services\FmisLoanPaymentApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncLoanPaymentsFromFmis extends Command
{
    protected $signature = 'loan-payments:sync-from-fmis
        {--full : Ignore the saved since-cursor and re-pull from the beginning}
        {--year= : Restrict the sync to a single year}
        {--per-page=200 : Page size for the API call}
        {--concurrency=8 : Number of pages to fetch in parallel}
        {--dry-run : Fetch and report without writing}
        {--no-update-cursor : Run without advancing the nightly since-cursor (manual syncs)}';

    protected $description = 'Pull payroll-deduction loan payments from the FMIS api-center and upsert into fmis_loan_payments.';

    private const CURSOR_KEY = 'fmis_loan_payments.last_sync_at';

    public function handle(FmisLoanPaymentApiClient $client): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;

        // An explicit --year always implies a full-year pull — don't filter by
        // the delta cursor (which would empty older years that pre-date it).
        $useCursor = !$this->option('full') && $year === null;
        $since = $useCursor ? Cache::get(self::CURSOR_KEY) : null;

        if (is_string($since)) {
            $since = Carbon::parse($since);
        }

        $perPage = max(1, (int) $this->option('per-page'));
        $concurrency = max(1, (int) $this->option('concurrency'));
        $dryRun = (bool) $this->option('dry-run');

        $userMap = User::query()
            ->whereNotNull('employee_id')
            ->pluck('id', 'employee_id')
            ->all();

        $runStartedAt = now();

        $this->line(sprintf(
            'Syncing FMIS loan payments — since=%s year=%s per_page=%d concurrency=%d%s (registered users=%d)',
            $since?->toIso8601String() ?? 'NULL',
            $year ?? 'all',
            $perPage,
            $concurrency,
            $dryRun ? ' [dry-run]' : '',
            count($userMap)
        ));

        // Fetch page 1 first so we know the total page count.
        $first = $client->fetchPage($since, 1, $perPage, $year);
        if ($first === null) {
            $this->error('Page 1 failed; aborting without updating cursor.');
            return self::FAILURE;
        }

        $totals = ['raw' => 0, 'registered' => 0, 'unregistered' => 0];
        $lastPage = (int) ($first['meta']['last_page'] ?? 1);

        $this->writeBatch($first['data'], $userMap, $dryRun, $totals);
        $this->line(sprintf('  page 1/%d — %d rows', $lastPage, count($first['data'])));

        $remaining = $lastPage > 1 ? range(2, $lastPage) : [];
        foreach (array_chunk($remaining, $concurrency) as $chunk) {
            $batches = $client->fetchPagesPool($chunk, $since, $perPage, $year);

            foreach ($chunk as $pageNum) {
                $batch = $batches[$pageNum] ?? null;
                if ($batch === null) {
                    $this->warn("  page {$pageNum}/{$lastPage} — failed, skipping");
                    continue;
                }
                $this->writeBatch($batch['data'], $userMap, $dryRun, $totals);
                $this->line(sprintf('  page %d/%d — %d rows', $pageNum, $lastPage, count($batch['data'])));
            }
        }

        $updateCursor = !$dryRun && !$this->option('no-update-cursor');
        if ($updateCursor) {
            Cache::forever(self::CURSOR_KEY, $runStartedAt->toIso8601String());
        }

        $this->info(sprintf(
            'Done — %d raw rows, %d registered, %d unregistered. Cursor=%s',
            $totals['raw'],
            $totals['registered'],
            $totals['unregistered'],
            $updateCursor ? $runStartedAt->toIso8601String() : '(unchanged)'
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $userMap
     * @param  array{raw:int,registered:int,unregistered:int}  &$totals
     */
    private function writeBatch(array $rows, array $userMap, bool $dryRun, array &$totals): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        $fmisRows = [];
        $registered = 0;
        $unregistered = 0;

        foreach ($rows as $row) {
            $empId = (string) ($row['employee_id'] ?? '');
            if ($empId === '') {
                continue;
            }

            $year = (int) ($row['year'] ?? 0);
            $month = (int) ($row['month'] ?? 0);
            $voided = (bool) ($row['voided'] ?? false);
            $amount = $voided ? 0 : ($row['amount'] ?? 0);

            $fmisRows[] = [
                'employee_id' => $empId,
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'dv_number' => $row['dv_number'] ?? null,
                'dv_date' => $row['dv_date'] ?? null,
                'fund' => $row['fund'] ?? null,
                'voided' => $voided,
                'fmis_updated_at' => $row['updated_at'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            isset($userMap[$empId]) ? $registered++ : $unregistered++;
        }

        $totals['raw'] += count($fmisRows);
        $totals['registered'] += $registered;
        $totals['unregistered'] += $unregistered;

        if ($dryRun || $fmisRows === []) {
            return;
        }

        // FMIS sends one row per DV — a single (employee, month) may now have
        // multiple DVs (e.g. 1st + 2nd half payroll). Unique key is the DV.
        FmisLoanPayment::upsert(
            $fmisRows,
            ['employee_id', 'dv_number'],
            ['amount', 'year', 'month', 'dv_date', 'fund', 'voided', 'fmis_updated_at', 'updated_at']
        );
    }
}
