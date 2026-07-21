<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Dependent;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the Ctrl+K command palette.
 *
 * Deliberately narrow: a few best matches per entity type for a keystroke-fast
 * jump list, not a replacement for the paginated list views.
 *
 * Every group is scoped to what the caller may legitimately see — members are
 * staff-only, and a member's own records never widen to anyone else's.
 */
class SearchController extends Controller
{
    use ApiResponse;

    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:80']);

        $term = trim($request->input('q'));
        $user = $request->user();
        $isStaff = in_array($user->role, ['admin', 'receiver', 'loan_committee', 'chairperson'], true);

        return $this->success([
            'members' => $isStaff ? $this->members($term) : [],
            'loans' => $this->loans($term, $user, $isStaff),
            'payments' => $this->payments($term, $user, $isStaff),
            'claims' => $this->claims($term, $user),
            'dependents' => $this->dependents($term, $user),
        ]);
    }

    /**
     * Members are staff-only — a member must never be able to enumerate
     * colleagues through the palette.
     */
    private function members(string $term): array
    {
        return User::where(fn ($q) => $q
            ->where('employee_id', 'LIKE', "%{$term}%")
            ->orWhere('first_name', 'LIKE', "%{$term}%")
            ->orWhere('last_name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%"))
            ->orderBy('last_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'title' => $u->full_name,
                'subtitle' => trim("{$u->employee_id} · {$u->employment_type}", ' ·'),
                'url' => "/admin/members/{$u->id}",
            ])
            ->all();
    }

    /**
     * Members see only their own loans (including ones they co-make);
     * staff see everyone's.
     */
    private function loans(string $term, User $user, bool $isStaff): array
    {
        $query = Loan::with('user');

        if (!$isStaff) {
            $query->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhere('co_maker_id', $user->id));
        }

        $query->where(function ($q) use ($term, $isStaff) {
            $q->where('loan_type', 'LIKE', "%{$term}%")
                ->orWhere('purpose', 'LIKE', "%{$term}%")
                ->orWhere('status', 'LIKE', "%{$term}%");

            if ($id = $this->numericTail($term)) {
                $q->orWhere('id', $id);
            }

            if ($isStaff) {
                $q->orWhereHas('user', fn ($u) => $u
                    ->where('employee_id', 'LIKE', "%{$term}%")
                    ->orWhere('first_name', 'LIKE', "%{$term}%")
                    ->orWhere('last_name', 'LIKE', "%{$term}%"));
            }
        });

        return $query->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Loan $loan) => [
                'id' => $loan->id,
                'title' => $loan->reference_no . ' · ' . $this->peso($loan->amount),
                'subtitle' => trim(
                    ($isStaff ? ($loan->user?->full_name ?? 'Unknown') . ' · ' : '')
                    . $loan->loan_type . ' · ' . str_replace('_', ' ', $loan->status)
                ),
                'url' => "/loans/{$loan->id}",
            ])
            ->all();
    }

    /**
     * Payments are found by OR number, method or amount. A member only ever
     * sees payments recorded against their own loans.
     */
    private function payments(string $term, User $user, bool $isStaff): array
    {
        $query = Payment::with('loan.user');

        if (!$isStaff) {
            $query->whereHas('loan', fn ($l) => $l->where('user_id', $user->id));
        }

        $query->where(function ($q) use ($term, $isStaff) {
            $q->where('or_number', 'LIKE', "%{$term}%")
                ->orWhere('payment_method', 'LIKE', "%{$term}%")
                ->orWhere('remarks', 'LIKE', "%{$term}%");

            // Amounts are matched as a prefix so typing "83333" finds
            // 83,333.33 — an exact comparison would only hit round figures.
            $numeric = str_replace(',', '', $term);
            if (is_numeric($numeric)) {
                $q->orWhereRaw('CAST(amount AS CHAR) LIKE ?', ["{$numeric}%"]);
            }

            if ($isStaff) {
                $q->orWhereHas('loan.user', fn ($u) => $u
                    ->where('employee_id', 'LIKE', "%{$term}%")
                    ->orWhere('last_name', 'LIKE', "%{$term}%"));
            }
        });

        return $query->latest('payment_date')
            ->limit(self::LIMIT)
            ->get()
            // A payment has no page of its own — send the user to its loan.
            ->filter(fn (Payment $p) => $p->loan !== null)
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'title' => $this->peso($p->amount) . ($p->or_number ? " · OR {$p->or_number}" : ''),
                'subtitle' => trim(
                    ($isStaff ? ($p->loan->user?->full_name ?? 'Unknown') . ' · ' : '')
                    . ($p->payment_date?->format('d M Y') ?? '')
                    . ' · ' . $p->loan->reference_no
                ),
                'url' => "/loans/{$p->loan_id}",
            ])
            ->values()
            ->all();
    }

    /**
     * Own claims only — there is no admin claims page to link to, so a
     * cross-member result would have nowhere valid to go.
     */
    private function claims(string $term, User $user): array
    {
        return Claim::where('user_id', $user->id)
            ->where(fn ($q) => $q
                ->where('claim_type', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('status', 'LIKE', "%{$term}%"))
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Claim $c) => [
                'id' => $c->id,
                'title' => $c->claim_type . ' · ' . $this->peso($c->amount),
                'subtitle' => ucfirst((string) $c->status) . ($c->description ? " · {$c->description}" : ''),
                'url' => '/claims',
            ])
            ->all();
    }

    /** Own dependents only, for the same reason as claims. */
    private function dependents(string $term, User $user): array
    {
        return Dependent::where('user_id', $user->id)
            ->where(fn ($q) => $q
                ->where('first_name', 'LIKE', "%{$term}%")
                ->orWhere('last_name', 'LIKE', "%{$term}%")
                ->orWhere('relationship', 'LIKE', "%{$term}%"))
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Dependent $d) => [
                'id' => $d->id,
                'title' => trim("{$d->first_name} {$d->last_name}"),
                'subtitle' => trim(ucfirst((string) $d->relationship) . ' · ' . ($d->coverage_type ?? ''), ' ·'),
                'url' => '/dependents',
            ])
            ->all();
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Reference numbers like "TXN-2026-000042" are derived, not stored, so
     * match their numeric tail against the record id.
     */
    private function numericTail(string $term): ?int
    {
        if (!preg_match('/(\d+)$/', $term, $m)) {
            return null;
        }

        $id = (int) ltrim($m[1], '0');

        return $id > 0 ? $id : null;
    }

    private function peso($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}
