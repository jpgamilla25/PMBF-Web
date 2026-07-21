<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action',
        'subject',
        'description',
        'old_value',
        'new_value',
        'employment_type',
        'ip_address',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Capture the requesting IP automatically unless one was supplied.
     */
    protected static function booted(): void
    {
        static::creating(function (self $log) {
            if (blank($log->ip_address) && app()->bound('request')) {
                $log->ip_address = request()->ip();
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Quick helper to record an activity.
     */
    public static function record(array $attributes): self
    {
        return self::create($attributes);
    }
}
