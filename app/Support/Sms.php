<?php

namespace App\Support;

use App\Models\SmsQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS delivery through the Android SMS Gateway (capcom6) — the same gateway
 * the LOKA reference client uses (a phone with a SIM behind an HTTP API).
 *
 * Contract (see Reference/sms.md):
 *   POST {baseUrl}{apiPath}        JSON {"textMessage":{"text":…},"phoneNumbers":[…]}
 *   Authorization: Basic base64(user:pass)      success = HTTP 2xx
 *
 * Design: enqueue on notify, send asynchronously (`php artisan sms:send`,
 * scheduled every minute). Business actions never block on the gateway and
 * never fail because an SMS failed.
 *
 * Config (env): SMS_ENABLED, SMS_GATEWAY_URL, SMS_GATEWAY_USERNAME,
 * SMS_GATEWAY_PASSWORD, SMS_API_PATH, SMS_DEFAULT_COUNTRY_CODE,
 * SMS_TIMEOUT_SECONDS, SMS_MAX_MESSAGE_LENGTH.
 */
class Sms
{
    public static function enabled(): bool
    {
        return (bool) config('services.sms.enabled', env('SMS_ENABLED', false));
    }

    public static function gatewayUrl(): string
    {
        return rtrim((string) config('services.sms.url', env('SMS_GATEWAY_URL', 'https://api.sms-gate.app')), '/');
    }

    public static function apiPath(): string
    {
        return (string) config('services.sms.path', env('SMS_API_PATH', '/3rdparty/v1/messages'));
    }

    public static function maxMessageLength(): int
    {
        return (int) config('services.sms.max_length', env('SMS_MAX_MESSAGE_LENGTH', 320));
    }

    /**
     * Normalise a PH mobile number to E.164 (+63…). Accepts local formats
     * (0917…, 63917…, +63917…). Landline/edge formats are not handled.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 0) {
            return null;
        }

        $countryCode = (int) config('services.sms.country_code', env('SMS_DEFAULT_COUNTRY_CODE', 63));

        // Already E.164 with a leading +.
        if (str_starts_with(trim($phone), '+')) {
            return '+' . $digits;
        }

        // Local leading zero, e.g. 0917… → +63 917…
        if (str_starts_with($digits, '0')) {
            return '+' . $countryCode . substr($digits, 1);
        }

        // Bare country code, e.g. 63917… → +63917…
        if (str_starts_with($digits, (string) $countryCode)) {
            return '+' . $digits;
        }

        return '+' . $countryCode . $digits;
    }

    /**
     * Queue a message for the gateway worker (never sends synchronously).
     */
    public static function enqueue(string $phone, string $message): ?SmsQueue
    {
        $e164 = self::normalizePhone($phone);
        if (! $e164) {
            return null;
        }

        return SmsQueue::create([
            'phone' => $e164,
            'message' => mb_substr($message, 0, self::maxMessageLength()),
            'status' => SmsQueue::STATUS_PENDING,
        ]);
    }

    /**
     * Deliver one queued message to the gateway. Returns true on 2xx.
     */
    public static function sendOne(SmsQueue $job): bool
    {
        $username = config('services.sms.username', env('SMS_GATEWAY_USERNAME', ''));
        $password = config('services.sms.password', env('SMS_GATEWAY_PASSWORD', ''));
        $timeout = (int) config('services.sms.timeout', env('SMS_TIMEOUT_SECONDS', 15));

        try {
            $response = Http::withBasicAuth($username, $password)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post(self::gatewayUrl() . self::apiPath(), [
                    'textMessage' => ['text' => $job->message],
                    'phoneNumbers' => [$job->phone],
                ]);

            if ($response->successful()) {
                $job->update([
                    'status' => SmsQueue::STATUS_SENT,
                    'sent_at' => now(),
                ]);

                return true;
            }

            $job->update([
                'status' => SmsQueue::STATUS_FAILED,
                'error' => 'HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS gateway unreachable', ['job' => $job->id, 'error' => $e->getMessage()]);

            $job->update([
                'status' => SmsQueue::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 400),
            ]);
        }

        return false;
    }
}
