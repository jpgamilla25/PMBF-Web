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
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

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
