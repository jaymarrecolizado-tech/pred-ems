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

    /**
     * Coerce a stored setting to a strict boolean. Setting values are stored
     * as JSON, so real booleans arrive as booleans — but this also guards
     * against string forms ('false', '0') ever being written.
     */
    private static function asBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether email notifications are switched on for the office
     * (admin-controlled; default ON).
     */
    public static function emailNotificationsEnabled(): bool
    {
        return self::asBool(static::get('notifications.email_enabled', null), true);
    }

    /**
     * Whether SMS notifications are switched on for the office
     * (admin-controlled). When unset, falls back to the SMS gateway state
     * (SMS_ENABLED) so it stays authoritative until an admin decides.
     */
    public static function smsNotificationsEnabled(): bool
    {
        $stored = static::get('notifications.sms_enabled', null);

        if ($stored !== null) {
            return self::asBool($stored, false);
        }

        return \App\Support\Sms::enabled();
    }

    /**
     * The set of notification channels currently switched on — used by the
     * settings page and by notification dispatch.
     */
    public static function notificationChannels(): array
    {
        return [
            'email' => self::emailNotificationsEnabled(),
            'sms' => self::smsNotificationsEnabled(),
        ];
    }
}
