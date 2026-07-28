<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Console\Command;

/**
 * Recompute stored monthly_amortization + total_payable from each loan's
 * persisted rate, so the header figures match the amortization schedule
 * (which recomputes from that same rate).
 *
 * Needed for loans created before the interest rate was stored at 4 decimals:
 * their figures came from a higher-precision rate (e.g. exact 8/12) and now
 * sit a centavo off the schedule. Deterministic and safe to re-run — remaining
 * balance is derived (total_payable - paid), so it self-corrects.
 */
class ReconcileLoanFigures extends Command
{
    protected $signature = 'loans:reconcile-figures {--dry-run : Show what would change without saving}';
    protected $description = 'Align stored monthly_amortization/total_payable with the schedule computed from the stored rate';

    public function handle(LoanService $loans): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (Loan::cursor() as $loan) {
            $figures = $loans->loanFigures(
                (float) $loan->amount,
                (float) $loan->interest_rate,
                (float) $loan->term_months,
                $loan->interest_method ?? 'flat',
            );

            $newMonthly = round($figures['monthly'], 2);
            $newTotal = round($figures['total'], 2);

            $monthlyOff = abs((float) $loan->monthly_amortization - $newMonthly) >= 0.01;
            $totalOff = abs((float) $loan->total_payable - $newTotal) >= 0.01;

            if (!$monthlyOff && !$totalOff) {
                continue;
            }

            $this->line(sprintf(
                '#%d %s: monthly %s→%s, total %s→%s',
                $loan->id,
                $loan->reference_no,
                number_format((float) $loan->monthly_amortization, 2),
                number_format($newMonthly, 2),
                number_format((float) $loan->total_payable, 2),
                number_format($newTotal, 2),
            ));

            if (!$dryRun) {
                // Bypass model events/timestamps — a pure figure correction.
                Loan::where('id', $loan->id)->update([
                    'monthly_amortization' => $newMonthly,
                    'total_payable' => $newTotal,
                ]);
            }

            $changed++;
        }

        $this->info(sprintf('%s %d loan%s.', $dryRun ? 'Would update' : 'Updated', $changed, $changed === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
