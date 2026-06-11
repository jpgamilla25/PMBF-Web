<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleRun extends Model
{
    protected $fillable = [
        'command',
        'expression',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'exit_code',
        'output_excerpt',
        'manual',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'exit_code' => 'integer',
            'manual' => 'boolean',
        ];
    }
}
