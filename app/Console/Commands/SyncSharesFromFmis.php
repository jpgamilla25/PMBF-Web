<?php

namespace App\Console\Commands;

use App\Models\EmploymentStint;
use App\Models\FmisShareContribution;
use App\Models\ShareCapital;
use App\Models\User;
use App\Services\HrisService;
use App\Services\FmisShareApiClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncSharesFromFmis extends Command
{
    protected $signature = 'shares:sync-from-fmis
        {--full : Ignore the saved since-cursor and re-pull from the beginning}
        {--year= : Restrict the sync to a single year}
        {--per-page=200 : Page size for the API call}
        {--concurrency=8 : Number of pages to fetch in parallel}
        {--dry-run : Fetch and report without writing}
        {--no-update-cursor : Run without advancing the nightly since-cursor (manual syncs)}';

    protected $description = 'Pull contributions from the FMIS api-center. Raw rows go to fmis_share_contributions; per-user rows are routed by employment_type to share_capitals (Permanent) or premiums (Contract of Service).';

    private const CURSOR_KEY = 'fmis_shares.last_sync_at';

    public function handle(FmisShareApiClient $client): int
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

        // Employee_id → [id, employment_type]. employment_type is the fallback
        // when local stint history is missing for that employee.
        $userMap = User::query()
            ->whereNotNull('employee_id')
            ->get(['id', 'employee_id', 'employment_type'])
            ->mapWithKeys(fn ($u) => [$u->employee_id => ['id' => $u->id, 'employment_type' => $u->employment_type]])
            ->all();

        // Prefetch all local stints keyed by employee_id — one query, no N+1.
        // The row-type decision below prefers stints (historically accurate)
        // and falls back to the current snapshot only when a member has no
        // local stint data (never synced or HRIS was down when they were).
        $stintsByEmployee = EmploymentStint::whereIn('employee_id', array_keys($userMap))
            ->orderBy('start_date')
            ->get()
            ->groupBy('employee_id');

        $runStartedAt = now();

        $this->line(sprintf(
            'Syncing FMIS shares — since=%s year=%s per_page=%d concurrency=%d%s (registered users=%d)',
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

        $totals = ['raw' => 0, 'registered' => 0, 'unregistered' => 0, 'shares' => 0, 'premiums' => 0, 'fallback' => 0];
        $lastPage = (int) ($first['meta']['last_page'] ?? 1);

        $this->writeBatch($first['data'], $userMap, $stintsByEmployee, $dryRun, $totals);
        $this->line(sprintf('  page 1/%d — %d rows', $lastPage, count($first['data'])));

        // Fan the remaining pages out in parallel batches.
        $remaining = $lastPage > 1 ? range(2, $lastPage) : [];
        foreach (array_chunk($remaining, $concurrency) as $chunk) {
            $batches = $client->fetchPagesPool($chunk, $since, $perPage, $year);

            foreach ($chunk as $pageNum) {
                $batch = $batches[$pageNum] ?? null;
                if ($batch === null) {
                    $this->warn("  page {$pageNum}/{$lastPage} — failed, skipping");
                    continue;
                }
                $this->writeBatch($batch['data'], $userMap, $stintsByEmployee, $dryRun, $totals);
                $this->line(sprintf('  page %d/%d — %d rows', $pageNum, $lastPage, count($batch['data'])));
            }
        }

        $updateCursor = !$dryRun && !$this->option('no-update-cursor');
        if ($updateCursor) {
            Cache::forever(self::CURSOR_KEY, $runStartedAt->toIso8601String());
        }

        $this->info(sprintf(
            'Done — %d raw rows, %d registered (%d shares + %d premiums; %d snapshot-fallback), %d unregistered (kept in fmis_share_contributions only). Cursor=%s',
            $totals['raw'],
            $totals['registered'],
            $totals['shares'],
            $totals['premiums'],
            $totals['fallback'],
            $totals['unregistered'],
            $updateCursor ? $runStartedAt->toIso8601String() : '(unchanged)'
        ));

        return self::SUCCESS;
    }

    /**
     * Build per-page batch arrays and flush via single upsert() calls.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, array{id:int,employment_type:?string}>  $userMap
     * @param  \Illuminate\Support\Collection  $stintsByEmployee employee_id => Collection<EmploymentStint>
     * @param  array{raw:int,registered:int,unregistered:int,shares:int,premiums:int,fallback:int}  &$totals
     */
    private function writeBatch(array $rows, array $userMap, \Illuminate\Support\Collection $stintsByEmployee, bool $dryRun, array &$totals): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        $fmisRows = [];
        // ledgerAggregates keyed on "empId|year|month" so multiple DVs for the
        // same member/month collapse to one share_capitals row.
        $ledgerAggregates = [];
        $fallbackCount = 0;
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
            // api-center emits the DV number as `DVControlNo` (matching the
            // source table's column). Fall back to `dv_number` for older
            // deployments that still use the pre-rename key.
            $dvNumber = $row['DVControlNo'] ?? $row['dv_number'] ?? null;
            $fund     = $row['fund'] ?? null;
            // api-center now emits one row per DV_Details line with a 1-based
            // line_no per (employee, dv, fund). Missing (legacy payload) → 1.
            $lineNo   = (int) ($row['line_no'] ?? 1);

            // Uniqueness is now (employee, dv_number, fund, line_no). We still
            // require dv_number since it anchors the whole grouping.
            if ($dvNumber === null || $dvNumber === '' || $fund === null || $fund === '') {
                continue;
            }

            $fmisRows[] = [
                'employee_id' => $empId,
                'year' => $year,
                'month' => $month,
                'amount' => $amount,
                'dv_number' => $dvNumber,
                'dv_date' => $this->toDate($row['dv_date'] ?? null),
                'fund' => $fund,
                'line_no' => $lineNo,
                'voided' => $voided,
                'fmis_updated_at' => $this->toDateTime($row['updated_at'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $user = $userMap[$empId] ?? null;
            if ($user === null) {
                $unregistered++;
                continue;
            }
            $registered++;

            // Prefer the local stint history — it's tagged historically,
            // so a re-sync of a promoted member's 2010 month gets 'premium'
            // (their COS stint then) instead of 'share' (their current
            // snapshot). Fall back to the snapshot only when no local
            // stints exist yet for that employee.
            $stints = $stintsByEmployee->get($empId);
            if ($stints && $stints->isNotEmpty()) {
                $type = \App\Services\HrisService::stintTypeAt($stints, $year, $month);
            } else {
                $type = match ($user['employment_type']) {
                    'Permanent' => 'share',
                    'Contract of Service' => 'premium',
                    default => null, // Non-Member — raw FMIS only, no member ledger
                };
                if ($type !== null) $fallbackCount++;
            }

            if ($type === null) {
                continue;
            }

            // One aggregate per (member, year, month) — the actual amount
            // gets rebuilt as SUM(FMIS) after we persist the DV rows.
            $key = "{$empId}|{$year}|{$month}";
            if (!isset($ledgerAggregates[$key])) {
                $ledgerAggregates[$key] = [
                    'user_id'     => $user['id'],
                    'employee_id' => $empId,
                    'year'        => $year,
                    'month'       => $month,
                    'type'        => $type,
                ];
            }
        }

        $totals['raw'] += count($fmisRows);
        $totals['registered'] += $registered;
        $totals['unregistered'] += $unregistered;
        $totals['fallback'] += $fallbackCount;

        if ($dryRun || $fmisRows === []) {
            return;
        }

        FmisShareContribution::upsert(
            $fmisRows,
            ['employee_id', 'dv_number', 'fund', 'line_no'],
            ['amount', 'year', 'month', 'dv_date', 'voided', 'fmis_updated_at', 'updated_at']
        );

        if ($ledgerAggregates === []) {
            return;
        }

        // Rebuild share_capitals.amount as SUM(FMIS.amount) for every touched
        // (employee, year, month). Captures split-cutoff DVs that would've
        // been silently overwritten under the old unique key.
        $touchedEmpIds  = array_values(array_unique(array_column($ledgerAggregates, 'employee_id')));
        $touchedYears   = array_values(array_unique(array_column($ledgerAggregates, 'year')));
        $touchedMonths  = array_values(array_unique(array_column($ledgerAggregates, 'month')));

        $sums = FmisShareContribution::whereIn('employee_id', $touchedEmpIds)
            ->whereIn('year', $touchedYears)
            ->whereIn('month', $touchedMonths)
            ->selectRaw('employee_id, year, month, SUM(amount) AS total, COUNT(*) AS dv_count')
            ->groupBy('employee_id', 'year', 'month')
            ->get()
            ->keyBy(fn ($r) => "{$r->employee_id}|{$r->year}|{$r->month}");

        $ledgerRows = [];
        $shareCount = 0;
        $premiumCount = 0;
        foreach ($ledgerAggregates as $key => $agg) {
            $sum = $sums->get($key);
            if (!$sum) continue;
            $ledgerRows[] = [
                'user_id'    => $agg['user_id'],
                'year'       => $agg['year'],
                'month'      => $agg['month'],
                'amount'     => $sum->total,
                'type'       => $agg['type'],
                'remarks'    => sprintf('Aggregated from %d FMIS DV(s)', $sum->dv_count),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($agg['type'] === 'share')   $shareCount++;
            if ($agg['type'] === 'premium') $premiumCount++;
        }

        $totals['shares']   += $shareCount;
        $totals['premiums'] += $premiumCount;

        if ($ledgerRows !== []) {
            // `type` intentionally not in the update columns — a re-sync of an
            // existing (user, year, month) row preserves the historical type.
            ShareCapital::upsert(
                $ledgerRows,
                ['user_id', 'year', 'month'],
                ['amount', 'remarks', 'updated_at']
            );
        }
    }

    /**
     * Normalize an FMIS API datetime (ISO-8601, e.g. "2026-01-06T00:00:00+00:00")
     * into a MySQL-storable "Y-m-d H:i:s" string. Null/invalid values become null.
     */
    private function toDateTime(mixed $value): ?string
    {
        return $this->parseOrNull($value)?->toDateTimeString();
    }

    private function toDate(mixed $value): ?string
    {
        return $this->parseOrNull($value)?->toDateString();
    }

    private function parseOrNull(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
