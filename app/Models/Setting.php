<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    /**
     * Container key for the per-request copy of the whole table.
     *
     * Money::format() asks for the currency on every amount it prints, and a
     * till screen prints dozens per render; one query for the table beats
     * one query per price. Scoped to the container so it lives for exactly
     * one request (and one test).
     */
    private const MEMO = 'settings.memo';

    public static function get(string $key, $default = null)
    {
        return static::memo()[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        $memo = static::memo();
        $memo[$key] = $value;
        app()->instance(self::MEMO, $memo);
    }

    /** @return array<string, mixed> key => value */
    private static function memo(): array
    {
        if (! app()->bound(self::MEMO)) {
            app()->instance(self::MEMO, static::query()->pluck('value', 'key')->all());
        }

        return app(self::MEMO);
    }
}
