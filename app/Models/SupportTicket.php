<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'employee_id',
        'email',
        'subject',
        'message',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'resolution_emailed',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'resolution_emailed' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * The member's name for the admin list. Falls back to the employee id
     * typed on the form when no account matches it — a ticket from someone
     * who is not yet a member is still worth answering.
     */
    public function getMemberNameAttribute(): string
    {
        return $this->user
            ? trim("{$this->user->first_name} {$this->user->last_name}")
            : 'Not a registered member';
    }

    /**
     * Next ticket number for the current year, e.g. TKT-2026-0007.
     *
     * Taken under a row lock on the year's last ticket so two people filing
     * at the same moment cannot be handed the same number — the UNIQUE index
     * is the final backstop.
     */
    public static function nextTicketNumber(): string
    {
        $year = now()->year;

        $last = static::withoutGlobalScopes()
            ->where('ticket_number', 'like', "TKT-{$year}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('ticket_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('TKT-%d-%04d', $year, $sequence);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /** Free-text search across the fields an admin would actually type. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (!$term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('ticket_number', 'like', "%{$term}%")
                ->orWhere('employee_id', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhere('message', 'like', "%{$term}%")
                ->orWhereHas('user', function (Builder $u) use ($term) {
                    $u->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
        });
    }
}
