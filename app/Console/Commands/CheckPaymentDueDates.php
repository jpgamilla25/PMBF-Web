<?php

namespace App\Console\Commands;

use App\Models\Configuration;
use App\Models\Loan;
use App\Notifications\PaymentDueNotification;
use Illuminate\Console\Command;

class CheckPaymentDueDates extends Command
{
    protected $signature = 'payments:check-due';
    protected $description = 'Check for upcoming and overdue loan payments, send notifications';

    public function handle(): int
    {
        $today = now()->startOfDay();

        // Get all released loans that are not fully paid
        $loans = Loan::with('user')
            ->where('status', 'released')
            ->get();

        $dueSoon = 0;
        $overdue = 0;
        $severe = 0;

        foreach ($loans as $loan) {
            if (!$loan->user || !$loan->released_at) {
                continue;
            }

            $nextDueDate = $this->getNextDueDate($loan);
            if (!$nextDueDate) {
                continue; // fully paid
            }

            $daysUntilDue = $today->diffInDays($nextDueDate, false);

            // Already notified today? Skip (check by notification type + date)
            $alreadyNotifiedToday = $loan->user->notifications()
                ->where('type', PaymentDueNotification::class)
                ->whereDate('created_at', $today)
                ->whereJsonContains('data->loan_id', $loan->id)
                ->exists();

            if ($alreadyNotifiedToday) {
                continue;
            }

            $severeThreshold = -(int) Configuration::getValue('payment_severely_overdue_days', 30);
            $dueSoonThreshold = (int) Configuration::getValue('payment_due_soon_days', 5);

            if ($daysUntilDue <= $severeThreshold) {
                $loan->user->notify(new PaymentDueNotification(
                    $loan, 'severely_overdue', $nextDueDate->format('M d, Y'), abs((int) $daysUntilDue)
                ));
                $severe++;
            } elseif ($daysUntilDue < 0) {
                $loan->user->notify(new PaymentDueNotification(
                    $loan, 'overdue', $nextDueDate->format('M d, Y'), abs((int) $daysUntilDue)
                ));
                $overdue++;
            } elseif ($daysUntilDue <= $dueSoonThreshold) {
                $loan->user->notify(new PaymentDueNotification(
                    $loan, 'due_soon', $nextDueDate->format('M d, Y')
                ));
                $dueSoon++;
            }
        }

        $this->info("Payment check complete: {$dueSoon} due soon, {$overdue} overdue, {$severe} severely overdue.");

        return Command::SUCCESS;
    }

    /**
     * Calculate the next payment due date for a loan.
     * Payments are due monthly from release date.
     * Returns null if loan is fully paid.
     */
    private function getNextDueDate(Loan $loan): ?\Carbon\Carbon
    {
        $totalPayable = $loan->monthly_amortization * $loan->term_months;
        $totalPaid = $loan->payments()->sum('amount');

        if ($totalPaid >= $totalPayable) {
            return null; // fully paid
        }

        $releaseDate = $loan->released_at;
        $paymentsMade = $loan->payments()->count();

        // Next due = release date + (payments_made + 1) months
        // If no payments yet, first due is 1 month after release
        return $releaseDate->copy()->addMonths($paymentsMade + 1)->startOfDay();
    }
}
