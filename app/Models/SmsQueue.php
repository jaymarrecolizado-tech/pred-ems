<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Outbound SMS queue row — delivered by `php artisan sms:send` against the
 * Android SMS Gateway (capcom6). Soft-fail by design: an SMS that cannot be
 * delivered never blocks the business action that enqueued it.
 */
class SmsQueue extends Model
{
    /** Eloquent would pluralise to `sms_queues`; the table is `sms_queue`. */
    protected $table = 'sms_queue';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'phone', 'message', 'status', 'error', 'attempts', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
