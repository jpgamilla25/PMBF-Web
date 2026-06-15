<?php

namespace App\Console\Commands;

use App\Services\LinkFmisLoanPaymentsService;
use Illuminate\Console\Command;

class LinkFmisLoanPayments extends Command
{
    protected $signature = 'loan-payments:link
        {--dry-run : Report what would happen without writing}';

    protected $description = 'Walk fmis_loan_payments and apply auto-matchable rows as payments against loans; queue ambiguous ones.';

    public function handle(LinkFmisLoanPaymentsService $linker): int
    {
        if ($this->option('dry-run')) {
            $this->warn('Dry-run mode — using a transaction we will rollback at the end.');

            \DB::beginTransaction();
            $result = $linker->linkPending();
            \DB::rollBack();
        } else {
            $result = $linker->linkPending();
        }

        $this->info(sprintf(
            'Scanned %d rows — linked %d, queued %d, voided-reversed %d.',
            $result['scanned'],
            $result['linked'],
            $result['queued'],
            $result['voided_reversed']
        ));

        return self::SUCCESS;
    }
}
