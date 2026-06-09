<?php

namespace App\Console\Commands;

use App\Models\ShareCapital;
use App\Models\User;
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
        {--dry-run : Fetch and report without writing to share_capitals}
        {--no-update-cursor : Run the sync without advancing the nightly since-cursor (admin-triggered manual syncs)}';

    protected $description = 'Pull share-capital contributions from the FMIS api-center and upsert into share_capitals.';

    private const CURSOR_KEY = 'fmis_shares.last_sync_at';

    public function handle(FmisShareApiClient $client): int
    {
        $since = $this->option('full')
            ? null
            : Cache::get(self::CURSOR_KEY);

        if (is_string($since)) {
            $since = Carbon::parse($since);
        }

        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $perPage = max(1, (int) $this->option('per-page'));
        $dryRun = (bool) $this->option('dry-run');

        $runStartedAt = now();
        $page = 1;
        $upserts = 0;
        $skipped = 0;
        $lastPage = 1;

        $this->line(sprintf(
            'Syncing FMIS shares — since=%s year=%s per_page=%d%s',
            $since?->toIso8601String() ?? 'NULL',
            $year ?? 'all',
            $perPage,
            $dryRun ? ' [dry-run]' : ''
        ));

        do {
            $batch = $client->fetchPage($since, $page, $perPage, $year);

            if ($batch === null) {
                $this->error("Page {$page} failed; aborting without updating cursor.");
                return self::FAILURE;
            }

            foreach ($batch['data'] as $row) {
                $result = $this->upsertRow($row, $dryRun);
                $result === 'upsert' ? $upserts++ : $skipped++;
            }

            $lastPage = (int) ($batch['meta']['last_page'] ?? 1);
            $this->line(sprintf('  page %d/%d — %d rows', $page, $lastPage, count($batch['data'])));
            $page++;
        } while ($page <= $lastPage);

        $updateCursor = !$dryRun && !$this->option('no-update-cursor');

        if ($updateCursor) {
            Cache::forever(self::CURSOR_KEY, $runStartedAt->toIso8601String());
        }

        $this->info(sprintf(
            'Done — %d upserted, %d skipped (unknown employee). Cursor=%s',
            $upserts,
            $skipped,
            $updateCursor ? $runStartedAt->toIso8601String() : '(unchanged)'
        ));

        return self::SUCCESS;
    }

    private function upsertRow(array $row, bool $dryRun): string
    {
        $user = User::where('employee_id', $row['employee_id'] ?? '')->first();
        if (!$user) {
            return 'skip';
        }

        if ($dryRun) {
            return 'upsert';
        }

        ShareCapital::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => (int) ($row['year'] ?? 0),
                'month' => (int) ($row['month'] ?? 0),
            ],
            [
                'amount' => ($row['voided'] ?? false) ? 0 : ($row['amount'] ?? 0),
                'remarks' => sprintf(
                    'Synced from FMIS DV %s (fund: %s)',
                    $row['dv_number'] ?? 'n/a',
                    $row['fund'] ?? 'n/a'
                ),
            ]
        );

        return 'upsert';
    }
}
