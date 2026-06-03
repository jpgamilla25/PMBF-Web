<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\ShareCapital;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatbotService
{
    /**
     * Smart queries: intent → live DB data.
     * Each entry maps an intent keyword pattern to a callable
     * that returns real-time data to inject into the AI response.
     */
    public function resolveSmartQuery(string $message, ?User $user = null): ?array
    {
        $lower = mb_strtolower($message);

        // Interest rate query
        if ($this->matchesIntent($lower, ['interest rate', 'interest', 'anong interest', 'magkano interest', 'rate'])) {
            return $this->getInterestRates($user);
        }

        // Loan limits / maximum amount
        if ($this->matchesIntent($lower, ['maximum loan', 'max loan', 'loan limit', 'how much can i borrow', 'magkano pwede', 'max amount', 'loan amount limit'])) {
            return $this->getLoanLimits($user);
        }

        // Available loan terms
        if ($this->matchesIntent($lower, ['loan term', 'available term', 'how long', 'ilang buwan', 'repayment period', 'months'])) {
            return $this->getLoanTerms($user);
        }

        // Loan status / my loans
        if ($user && $this->matchesIntent($lower, ['my loan', 'loan status', 'active loan', 'pending loan', 'mga loan ko', 'loan ko'])) {
            return $this->getUserLoans($user);
        }

        // Payment due / next payment
        if ($user && $this->matchesIntent($lower, ['payment due', 'next payment', 'when to pay', 'kailan bayad', 'due date', 'balance', 'remaining'])) {
            return $this->getPaymentInfo($user);
        }

        // Share capital
        if ($user && $this->matchesIntent($lower, ['share capital', 'my share', 'shares', 'savings'])) {
            return $this->getShareCapital($user);
        }

        // Compute / calculate amortization
        if ($this->matchesIntent($lower, ['compute', 'calculate', 'amortization', 'monthly payment', 'kalkulahin', 'magkano babayaran'])) {
            return $this->getComputationHelper($lower, $user);
        }

        // Loan types available for user
        if ($this->matchesIntent($lower, ['loan type', 'available loan', 'anong loan', 'types of loan', 'what loan can i'])) {
            return $this->getAvailableLoanTypes($user);
        }

        // Minimum pay requirement
        if ($this->matchesIntent($lower, ['minimum pay', 'minimum take home', 'min pay', 'take home pay', 'minimum salary'])) {
            return $this->getMinimumPayRequirements();
        }

        // Member count / stats (admin-friendly)
        if ($this->matchesIntent($lower, ['how many members', 'total members', 'member count', 'ilan members'])) {
            return $this->getMemberStats();
        }

        return null;
    }

    private function matchesIntent(string $message, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }
        return false;
    }

    // ── Smart Query Resolvers ────────────────────────────────────

    private function getInterestRates(?User $user): array
    {
        $rates = [
            'SC Members' => Configuration::getValue('interest_rate_sc', '1.50'),
            'Permanent Members' => Configuration::getValue('interest_rate_permanent', '1.00'),
            'Non-Members' => Configuration::getValue('interest_rate_non_member', '2.00'),
        ];

        $context = "**Current Interest Rates (Monthly, Flat):**\n";
        foreach ($rates as $type => $rate) {
            $context .= "• {$type}: **{$rate}%** per month\n";
        }

        if ($user) {
            $userType = $user->employment_type;
            $key = match ($userType) {
                'Contract of Service' => 'interest_rate_sc',
                'Permanent' => 'interest_rate_permanent',
                default => 'interest_rate_non_member',
            };
            $userRate = Configuration::getValue($key, '1.00');
            $context .= "\n👤 As a **{$userType}** member, your rate is **{$userRate}% per month**.";
        }

        return [
            'intent' => 'interest_rates',
            'data' => $rates,
            'context' => $context,
        ];
    }

    private function getLoanLimits(?User $user): array
    {
        $limits = [];

        if (!$user || $user->employment_type === 'Permanent') {
            $limits['Permanent'] = [
                'Consolidated' => Configuration::getValue('permanent_max_loan_consolidated', '200000'),
                'Multi-Purpose' => Configuration::getValue('permanent_max_loan_multipurpose', '100000'),
                'Emergency' => Configuration::getValue('permanent_max_loan_emergency', '30000'),
                'Hospitalization' => Configuration::getValue('permanent_max_loan_hospitalization', '50000'),
            ];
        }

        if (!$user || $user->employment_type === 'Contract of Service') {
            $limits['Contract of Service'] = [
                'Salary Loan' => Configuration::getValue('sc_max_loan_amount', '50000') . ' (or salary × contract months)',
            ];
        }

        if (!$user || $user->employment_type === 'Non-Member') {
            $limits['Non-Member'] = [
                'All Types' => Configuration::getValue('non_member_max_loan_amount', '30000'),
            ];
        }

        $context = "**Current Loan Limits:**\n";
        foreach ($limits as $type => $types) {
            $context .= "\n__{$type}:__\n";
            foreach ($types as $loanType => $max) {
                $formatted = is_numeric($max) ? '₱' . number_format((float)$max, 2) : $max;
                $context .= "• {$loanType}: {$formatted}\n";
            }
        }

        return [
            'intent' => 'loan_limits',
            'data' => $limits,
            'context' => $context,
        ];
    }

    private function getLoanTerms(?User $user): array
    {
        $terms = [
            'Contract of Service' => Configuration::getValue('sc_available_terms', '3,6,12'),
            'Permanent' => Configuration::getValue('permanent_available_terms', '3,6,12,18,24,36,48,60'),
            'Non-Member' => Configuration::getValue('non_member_available_terms', '3,6,12,18,24'),
        ];
        $maxTerm = Configuration::getValue('max_loan_term_months', '60');

        $context = "**Available Loan Terms:**\n";
        foreach ($terms as $type => $termList) {
            $context .= "• {$type}: {$termList} months\n";
        }
        $context .= "\nMaximum term: **{$maxTerm} months**";

        if ($user && $user->employment_type === 'Contract of Service') {
            $context .= "\n\n⚠️ Contract of Service terms are also limited by your remaining contract duration.";
        }

        return [
            'intent' => 'loan_terms',
            'data' => $terms,
            'context' => $context,
        ];
    }

    private function getUserLoans(User $user): array
    {
        $loans = Loan::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'loan_type', 'amount', 'status', 'term_months', 'monthly_amortization', 'applied_at', 'released_at']);

        $activeLoan = $loans->whereIn('status', ['approved', 'released'])->first();
        $pendingLoan = $loans->whereIn('status', ['pending', 'receiver_approved', 'committee_approved', 'co_maker_pending', 'admin_pending'])->first();

        $context = "**Your Recent Loans:**\n";
        if ($loans->isEmpty()) {
            $context .= "You don't have any loans yet.";
        } else {
            foreach ($loans as $loan) {
                $statusLabel = str_replace('_', ' ', ucfirst($loan->status));
                $amount = '₱' . number_format($loan->amount, 2);
                $context .= "• **{$loan->loan_type}** — {$amount} ({$loan->term_months} months) — Status: **{$statusLabel}**\n";
            }
        }

        if ($activeLoan) {
            $totalPayable = $activeLoan->total_payable;
            $totalPaid = Payment::where('loan_id', $activeLoan->id)->sum('amount');
            $remaining = $totalPayable - $totalPaid;
            $context .= "\n💰 Active loan remaining balance: **₱" . number_format(max(0, $remaining), 2) . "**";
        }

        return [
            'intent' => 'user_loans',
            'data' => $loans->toArray(),
            'context' => $context,
        ];
    }

    private function getPaymentInfo(User $user): array
    {
        $loan = Loan::where('user_id', $user->id)
            ->where('status', 'released')
            ->latest('released_at')
            ->first();

        if (!$loan) {
            return [
                'intent' => 'payment_info',
                'data' => null,
                'context' => "You don't have any active released loan right now.",
            ];
        }

        $totalPayable = $loan->total_payable;
        $payments = Payment::where('loan_id', $loan->id)->get();
        $totalPaid = $payments->sum('amount');
        $remaining = max(0, $totalPayable - $totalPaid);
        $paymentsMade = $payments->count();

        $nextDueDate = $loan->released_at->copy()->addMonths($paymentsMade + 1);
        $daysUntil = now()->diffInDays($nextDueDate, false);

        $context = "**Payment Info for your {$loan->loan_type}:**\n";
        $context .= "• Loan Amount: ₱" . number_format($loan->amount, 2) . "\n";
        $context .= "• Monthly Amortization: ₱" . number_format($loan->monthly_amortization, 2) . "\n";
        $context .= "• Total Payable: ₱" . number_format($totalPayable, 2) . "\n";
        $context .= "• Total Paid: ₱" . number_format($totalPaid, 2) . "\n";
        $context .= "• Remaining Balance: **₱" . number_format($remaining, 2) . "**\n";
        $context .= "• Payments Made: {$paymentsMade} of {$loan->term_months}\n";
        $context .= "• Next Payment Due: **" . $nextDueDate->format('F j, Y') . "**";

        if ($daysUntil < 0) {
            $context .= " ⚠️ **(OVERDUE by " . abs($daysUntil) . " days)**";
        } elseif ($daysUntil <= 5) {
            $context .= " ⏰ **(Due in {$daysUntil} days)**";
        }

        return [
            'intent' => 'payment_info',
            'data' => [
                'loan_type' => $loan->loan_type,
                'total_payable' => $totalPayable,
                'total_paid' => $totalPaid,
                'remaining' => $remaining,
                'next_due' => $nextDueDate->toDateString(),
            ],
            'context' => $context,
        ];
    }

    private function getShareCapital(User $user): array
    {
        $currentYear = now()->year;
        $shares = ShareCapital::where('user_id', $user->id)
            ->where('year', $currentYear)
            ->orderBy('month')
            ->get(['month', 'amount']);

        $total = $shares->sum('amount');
        $currentMonthly = ShareCapital::where('user_id', $user->id)
            ->orderByDesc('year')->orderByDesc('month')
            ->first()?->amount ?? 0;

        $context = "**Your Share Capital ({$currentYear}):**\n";
        $context .= "• Current Monthly Amount: ₱" . number_format($currentMonthly, 2) . "\n";
        $context .= "• Total for {$currentYear}: ₱" . number_format($total, 2) . "\n";

        if ($shares->isNotEmpty()) {
            $context .= "\nMonthly breakdown:\n";
            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            foreach ($shares as $share) {
                $context .= "• {$monthNames[$share->month]}: ₱" . number_format($share->amount, 2) . "\n";
            }
        }

        return [
            'intent' => 'share_capital',
            'data' => ['total' => $total, 'current_monthly' => $currentMonthly, 'entries' => $shares->toArray()],
            'context' => $context,
        ];
    }

    private function getComputationHelper(string $message, ?User $user): array
    {
        $context = "**Loan Computation Formula (Flat Interest):**\n\n";
        $context .= "```\n";
        $context .= "Total Interest = Principal × Monthly Rate × Term\n";
        $context .= "Monthly Amortization = (Principal + Total Interest) ÷ Term\n";
        $context .= "Total Payable = Monthly Amortization × Term\n";
        $context .= "```\n\n";

        // Try to extract numbers from the message for a sample computation
        preg_match_all('/[\d,]+\.?\d*/', str_replace(',', '', $message), $matches);
        $numbers = array_values(array_filter(array_map('floatval', $matches[0] ?? []), fn($n) => $n > 0));

        if (count($numbers) >= 2) {
            $principal = $numbers[0];
            $termMonths = (int) $numbers[1];
            $rateKey = 'interest_rate_permanent';
            $rateLabel = 'Permanent (1%)';

            if ($user) {
                $rateKey = match ($user->employment_type) {
                    'Contract of Service' => 'interest_rate_sc',
                    'Non-Member' => 'interest_rate_non_member',
                    default => 'interest_rate_permanent',
                };
                $rateLabel = $user->employment_type;
            }

            $rate = (float) Configuration::getValue($rateKey, '1.00');
            $totalInterest = $principal * ($rate / 100) * $termMonths;
            $monthlyAmort = round(($principal + $totalInterest) / $termMonths, 2);

            $context .= "**Sample Computation:**\n";
            $context .= "• Principal: ₱" . number_format($principal, 2) . "\n";
            $context .= "• Term: {$termMonths} months\n";
            $context .= "• Rate: {$rate}% ({$rateLabel})\n";
            $context .= "• Total Interest: ₱" . number_format($totalInterest, 2) . "\n";
            $context .= "• Monthly Amortization: **₱" . number_format($monthlyAmort, 2) . "**\n";
            $context .= "• Total Payable: ₱" . number_format($monthlyAmort * $termMonths, 2) . "\n";
        } else {
            $context .= "💡 Tip: Include the amount and term in your message for an instant calculation. Example: \"Compute 50000 for 12 months\"";
        }

        return [
            'intent' => 'computation',
            'data' => null,
            'context' => $context,
        ];
    }

    private function getAvailableLoanTypes(?User $user): array
    {
        $types = [];

        if (!$user || $user->employment_type === 'Contract of Service') {
            $types['Contract of Service'] = ['Salary Loan'];
        }
        if (!$user || $user->employment_type === 'Permanent') {
            $types['Permanent'] = ['Consolidated', 'Multi-Purpose', 'Emergency', 'Hospitalization'];
        }
        if (!$user || $user->employment_type === 'Non-Member') {
            $types['Non-Member'] = ['Salary Loan', 'Multi-Purpose', 'Emergency'];
        }

        $context = "**Available Loan Types:**\n";
        foreach ($types as $empType => $loanTypes) {
            $context .= "\n__{$empType}:__\n";
            foreach ($loanTypes as $lt) {
                $context .= "• {$lt}\n";
            }
        }

        if ($user) {
            $context .= "\n👤 As a **{$user->employment_type}** member, you can apply for: " .
                implode(', ', $types[$user->employment_type] ?? []);
        }

        return [
            'intent' => 'loan_types',
            'data' => $types,
            'context' => $context,
        ];
    }

    private function getMinimumPayRequirements(): array
    {
        $scMin = Configuration::getValue('sc_min_take_home_pay', '5000');
        $permMin = Configuration::getValue('permanent_min_take_home_pay', '5000');

        $context = "**Minimum Take-Home Pay Requirements:**\n";
        $context .= "• SC Members: ₱" . number_format((float) $scMin, 2) . "\n";
        $context .= "• Permanent Members: ₱" . number_format((float) $permMin, 2) . "\n";
        $context .= "\nIf your pay is below the minimum, you can apply for a **Below Minimum Pay** exemption.";

        return [
            'intent' => 'minimum_pay',
            'data' => ['sc' => $scMin, 'permanent' => $permMin],
            'context' => $context,
        ];
    }

    private function getMemberStats(): array
    {
        $total = User::count();
        $active = User::where('status', 'active')->count();
        $byType = User::where('status', 'active')
            ->select('employment_type', DB::raw('count(*) as count'))
            ->groupBy('employment_type')
            ->pluck('count', 'employment_type')
            ->toArray();

        $context = "**Member Statistics:**\n";
        $context .= "• Total Registered: {$total}\n";
        $context .= "• Active: {$active}\n";
        foreach ($byType as $type => $count) {
            $context .= "• {$type}: {$count}\n";
        }

        return [
            'intent' => 'member_stats',
            'data' => ['total' => $total, 'active' => $active, 'by_type' => $byType],
            'context' => $context,
        ];
    }
}
