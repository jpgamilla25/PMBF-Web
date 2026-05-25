<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppAsset extends Model
{
    protected $fillable = [
        'type', 'label', 'file_name', 'file_path', 'mime_type', 'file_size', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
