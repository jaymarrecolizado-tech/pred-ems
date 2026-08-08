<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Read a setting with a default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);

        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => is_string($value) ? $value : json_encode($value),
        ]);
    }

    /**
     * Office hours (default CSC 8–12 / 1–5 schedule) used by the DTR.
     */
    public static function officeHours(): array
    {
        return static::get('office_hours', [
            'am_start' => '08:00',
            'am_end' => '12:00',
            'pm_start' => '13:00',
            'pm_end' => '17:00',
        ]);
    }
}
