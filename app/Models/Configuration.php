<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    protected $fillable = [
        'key', 'value', 'type', 'group',
        'description', 'options', 'suffix', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public static function getValue(string $key, $default = null)
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->value : $default;
    }

    public static function getDecimal(string $key, float $default = 0): float
    {
        return (float) (static::getValue($key) ?? $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return (bool) (static::getValue($key) ?? $default);
    }

    public static function setValue(string $key, $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }
}
