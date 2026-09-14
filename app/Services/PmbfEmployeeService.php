<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates members who are employed by PMBF itself rather than by PhilRice.
 *
 * Every other member arrives through PhilRice HRIS: registration looks them up
 * by employee ID and the nightly sync keeps their snapshot fresh. PMBF's own
 * staff have no HRIS record at all, so an admin enters them by hand and the
 * system issues the member ID itself.
 */
class PmbfEmployeeService
{
    /**
     * Create a PMBF Employee member.
     *
     * The member ID is issued here rather than typed by the admin so it can
     * never collide with a PhilRice HRIS employee ID, and so two admins saving
     * at the same moment cannot land on the same number.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes.
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes) {
            return User::create([
                'employee_id' => $this->nextMemberId(),
                'first_name' => $attributes['first_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'last_name' => $attributes['last_name'],
                'suffix' => $attributes['suffix'] ?? null,
                'email' => $attributes['email'],
                'mobile' => $attributes['mobile'] ?? null,
                'employment_type' => 'PMBF Employee',
                'position' => $attributes['position'] ?? null,
                'department' => $attributes['department'] ?? null,
                'role' => 'member',
                'status' => $attributes['status'] ?? 'active',
                // Sign-in is by emailed OTP against the member ID, so no
                // password is ever used. A random one keeps the column
                // populated without leaving a guessable value behind.
                'password' => Str::random(40),
            ]);
        });
    }

    /**
     * Apply an admin's corrections to a PMBF Employee.
     *
     * Only the locally-entered fields move. The member ID is server-owned and
     * the employment type is what makes this record editable in the first
     * place, so neither is writable here.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes.
     * @return array<string, array{from: mixed, to: mixed}>  What actually changed.
     */
    public function update(User $user, array $attributes): array
    {
        $editable = [
            'first_name', 'middle_name', 'last_name', 'suffix',
            'email', 'mobile', 'position', 'department', 'status',
        ];

        $changes = [];

        foreach ($editable as $field) {
            if (!array_key_exists($field, $attributes)) {
                continue;
            }

            $incoming = $attributes[$field] === '' ? null : $attributes[$field];

            if ($user->{$field} !== $incoming) {
                $changes[$field] = ['from' => $user->{$field}, 'to' => $incoming];
                $user->{$field} = $incoming;
            }
        }

        if ($changes) {
            $user->save();
        }

        return $changes;
    }

    /**
     * The next member ID in the current year's sequence, e.g. PE-2026-0007.
     *
     * Must run inside a transaction — the row lock is what stops two
     * concurrent creates from reading the same highest number.
     */
    public function nextMemberId(): string
    {
        $prefix = User::PMBF_EMPLOYEE_ID_PREFIX . '-' . now()->year . '-';

        $highest = User::where('employee_id', 'LIKE', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('employee_id')
            ->value('employee_id');

        $sequence = $highest
            ? (int) Str::afterLast($highest, '-') + 1
            : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
