<?php

namespace App\Services;

use App\Models\User;

/**
 * Rule-based loan-application assistant.
 *
 * Deliberately contains no AI calls: intent and values are extracted with
 * plain pattern matching, and every number it quotes comes from LoanService,
 * so the chat can never promise terms the application flow would reject.
 *
 * Conversation state is derived from the message history rather than stored,
 * which keeps the endpoint stateless.
 */
class LoanChatService
{
    public function __construct(
        private readonly LoanService $loanService,
    ) {}

    /** Words that signal the member wants to borrow (incl. common Filipino). */
    private const INTENT_PATTERNS = [
        'loan', 'borrow', 'apply', 'avail', 'utang', 'hiram', 'mangutang', 'umutang',
    ];

    /**
     * Try to handle the message as a loan application.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, action: ?array, slots: array}|null  Null when this isn't a loan request.
     */
    public function handle(string $message, ?User $user, array $history = []): ?array
    {
        // Look at the whole conversation so values given across several turns
        // ("I want a loan" → "50k" → "3 months") accumulate.
        $transcript = $this->transcript($message, $history);

        if (!$this->looksLikeLoanRequest($transcript)) {
            return null;
        }

        if (!$user) {
            return $this->reply(
                "I can help you apply for a loan — but you'll need to sign in first so I can check "
                . "your eligibility against your HRIS record.",
                ['label' => 'Sign in', 'url' => '/login']
            );
        }

        $types = $this->loanService->getAvailableLoanTypes($user);

        if (empty($types)) {
            return $this->reply(
                "I couldn't find any loan types available for your employment type. "
                . "Please contact the PMBF office."
            );
        }

        $slots = [
            'loan_type' => $this->extractLoanType($transcript, array_keys($types)),
            'amount' => $this->extractAmount($transcript),
            'term_months' => $this->extractTerm($transcript),
        ];

        // ── Slot filling ──────────────────────────────────────
        // Only one type available? Don't make them pick.
        if ($slots['loan_type'] === null && count($types) === 1) {
            $slots['loan_type'] = array_key_first($types);
        }

        if ($slots['loan_type'] === null) {
            return $this->reply(
                "Sure — which loan would you like to apply for?\n\n"
                . $this->bulletList(array_map(
                    fn ($name, $info) => sprintf(
                        '**%s** — up to ₱%s at %s%%/month',
                        $name,
                        number_format((float) $info['max_amount'], 2),
                        rtrim(rtrim(number_format((float) $info['interest_rate'], 2), '0'), '.')
                    ),
                    array_keys($types),
                    $types
                )),
                null,
                $slots
            );
        }

        $typeInfo = $types[$slots['loan_type']];

        // ── Eligibility comes before anything else ────────────
        $eligibility = $this->loanService->checkEligibility(
            $user,
            $slots['loan_type'],
            (float) ($slots['amount'] ?? 0) ?: 1000.0,
            $slots['term_months']
        );

        if (!$eligibility['eligible']) {
            $reply = "I checked your record and you're **not currently eligible** for a "
                . "{$slots['loan_type']}.\n\n> {$eligibility['message']}";

            if (!empty($eligibility['can_request_exemption'])) {
                $reply .= "\n\nYou can ask the administrator for a special approval.";

                return $this->reply($reply, ['label' => 'Request special approval', 'url' => '/loans/new'], $slots);
            }

            return $this->reply($reply, null, $slots);
        }

        if ($slots['amount'] === null) {
            return $this->reply(
                "How much would you like to borrow? The maximum for a **{$slots['loan_type']}** is "
                . '₱' . number_format((float) $typeInfo['max_amount'], 2) . '.',
                null,
                $slots
            );
        }

        $terms = $typeInfo['available_terms'] ?? [];

        if (empty($terms)) {
            return $this->reply(
                "There are no loan terms available to you right now"
                . (isset($typeInfo['remaining_contract_months'])
                    ? " because your contract has {$typeInfo['remaining_contract_months']} month(s) remaining"
                    : '')
                . '. Please contact the PMBF office.',
                null,
                $slots
            );
        }

        if ($slots['term_months'] === null) {
            return $this->reply(
                'Over how many months would you like to repay? Available terms: **'
                . implode(', ', $terms) . '** months.',
                null,
                $slots
            );
        }

        // ── Validate the values against the real rules ────────
        // Loose compare: config terms may be int (6) or float (5.5).
        if (!in_array((float) $slots['term_months'], array_map('floatval', $terms), true)) {
            return $this->reply(
                "A **{$slots['term_months']}-month** term isn't available for a {$slots['loan_type']}. "
                . 'You can choose: **' . implode(', ', $terms) . '** months.',
                null,
                ['loan_type' => $slots['loan_type'], 'amount' => $slots['amount'], 'term_months' => null]
            );
        }

        $max = (float) $typeInfo['max_amount'];
        $overMax = $slots['amount'] > $max;

        // ── Everything checks out: quote it and hand off ──────
        $rate = (float) $typeInfo['interest_rate'];
        $monthly = $this->loanService->calculateAmortization($slots['amount'], $rate, $slots['term_months']);

        // Derived from the principal, not from the rounded monthly figure —
        // multiplying the rounded payment back out drifts by a few centavos.
        $totalPayable = $slots['amount'] + ($slots['amount'] * ($rate / 100) * $slots['term_months']);

        $reply = "Here's what that looks like:\n\n"
            . $this->bulletList([
                'Loan type: **' . $slots['loan_type'] . '**',
                'Amount: **₱' . number_format($slots['amount'], 2) . '**',
                'Term: **' . $slots['term_months'] . ' months**',
                'Interest: **' . rtrim(rtrim(number_format($rate, 2), '0'), '.') . '% / month**',
                'Monthly payment: **₱' . number_format($monthly, 2) . '**',
                'Total payable: **₱' . number_format($totalPayable, 2) . '**',
            ]);

        if ($overMax) {
            $reply .= "\n\n⚠️ This is above the ₱" . number_format($max, 2)
                . ' maximum, so it will need administrator approval before it goes to the approvers.';
        }

        if (!empty($typeInfo['requires_co_maker'])) {
            $reply .= "\n\nYou'll also need a **Permanent employee as co-maker** — you can pick them on the form.";
        }

        $reply .= "\n\nI've pre-filled the application for you. Review it and submit when you're ready.";

        return $this->reply($reply, [
            'label' => 'Open pre-filled application',
            'url' => '/loans/new?' . http_build_query([
                'loan_type' => $slots['loan_type'],
                'amount' => $slots['amount'],
                'term_months' => $slots['term_months'],
            ]),
        ], $slots);
    }

    // ─── Intent & extraction ──────────────────────────────────

    private function looksLikeLoanRequest(string $text): bool
    {
        foreach (self::INTENT_PATTERNS as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '/iu', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a loan type by name, tolerating spacing and punctuation
     * ("multi purpose" → "Multi-Purpose").
     *
     * @param  array<int, string>  $available
     */
    private function extractLoanType(string $text, array $available): ?string
    {
        $normalised = $this->normalise($text);

        // Longest names first so "Salary Loan" wins over a bare "loan".
        usort($available, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($available as $type) {
            if (str_contains($normalised, $this->normalise($type))) {
                return $type;
            }
        }

        // Common shorthands.
        $aliases = [
            'multipurpose' => 'Multi-Purpose',
            'multi purpose' => 'Multi-Purpose',
            'hospital' => 'Hospitalization',
            'medical' => 'Hospitalization',
            'emergency' => 'Emergency',
            'consolidate' => 'Consolidated',
            'salary' => 'Salary Loan',
            'regular' => 'Regular',
        ];

        foreach ($aliases as $alias => $type) {
            if (str_contains($normalised, $alias) && in_array($type, $available, true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Pull a peso amount out of free text: "50k", "50,000", "₱50000",
     * "50 thousand". Returns the last one mentioned, so a correction later in
     * the conversation wins.
     */
    private function extractAmount(string $text): ?float
    {
        $matches = [];

        // "50k" / "50 k" / "1.5k"
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*k\b/iu', $text, $m)) {
            foreach ($m[1] as $n) {
                $matches[] = (float) $n * 1000;
            }
        }

        // "50 thousand"
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:thousand|libo)\b/iu', $text, $m)) {
            foreach ($m[1] as $n) {
                $matches[] = (float) $n * 1000;
            }
        }

        // Plain or comma-grouped numbers, optionally with a peso sign.
        if (preg_match_all('/(?:₱|php\s*)?(\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d{4,}(?:\.\d+)?)/iu', $text, $m)) {
            foreach ($m[1] as $n) {
                $matches[] = (float) str_replace(',', '', $n);
            }
        }

        // Ignore values too small to be a loan — those are usually terms.
        $matches = array_values(array_filter($matches, fn ($v) => $v >= 1000));

        return $matches ? (float) end($matches) : null;
    }

    /** Pull a term in months, incl. halves: "3 months", "5.5mo", "3-month". */
    private function extractTerm(string $text): float|int|null
    {
        if (preg_match_all('/(\d{1,3}(?:\.\d+)?)\s*(?:-|\s)?\s*(?:months?|mos?\b|buwan)/iu', $text, $m)) {
            $last = (float) end($m[1]);
            if ($last <= 0) {
                return null;
            }

            // Whole number stays an int so "3 months" reads cleanly.
            return $last == (int) $last ? (int) $last : $last;
        }

        return null;
    }

    // ─── Helpers ──────────────────────────────────────────────

    /** Flatten the conversation so multi-turn answers accumulate. */
    private function transcript(string $message, array $history): string
    {
        $userTurns = array_map(
            fn ($turn) => (string) ($turn['content'] ?? ''),
            array_filter($history, fn ($turn) => ($turn['role'] ?? '') === 'user')
        );

        $userTurns[] = $message;

        return implode(' ', $userTurns);
    }

    private function normalise(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($text)));
    }

    private function bulletList(array $lines): string
    {
        return implode("\n", array_map(fn ($l) => "- {$l}", $lines));
    }

    private function reply(string $text, ?array $action = null, array $slots = []): array
    {
        return ['reply' => $text, 'action' => $action, 'slots' => $slots];
    }
}
