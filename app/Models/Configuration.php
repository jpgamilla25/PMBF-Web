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

    /**
     * A blank value means "not configured" and falls back to the default.
     * Without this, (float) '' would silently evaluate to 0 — which for a
     * rate or limit is a real, and very wrong, value.
     */
    public static function getDecimal(string $key, float $default = 0): float
    {
        $value = static::getValue($key);

        return ($value === null || trim((string) $value) === '') ? $default : (float) $value;
    }

    /**
     * Blank means "not configured" and falls back to the default — (bool) ''
     * is false, which would silently ignore a default of true.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function setValue(string $key, $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }
}
