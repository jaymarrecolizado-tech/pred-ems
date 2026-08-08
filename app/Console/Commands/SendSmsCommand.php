<?php

namespace App\Console\Commands;

use App\Models\SmsQueue;
use App\Support\Sms;
use Illuminate\Console\Command;

/**
 * `php artisan sms:send` — delivers the outbound SMS queue to the Android
 * gateway (capcom6). Scheduled every minute via routes/console.php; retries
 * failed rows up to 3 attempts so a temporarily offline phone doesn't lose
 * messages permanently.
 */
class SendSmsCommand extends Command
{
    protected $signature = 'sms:send {--limit=50 : Max messages to deliver per run}';

    protected $description = 'Deliver queued SMS messages to the Android SMS gateway';

    public function handle(): int
    {
        if (! Sms::enabled()) {
            // Silent no-op: this runs every minute, a warning would spam the
            // scheduler log while SMS is switched off in .env.
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $jobs = SmsQueue::query()
            ->whereIn('status', [SmsQueue::STATUS_PENDING, SmsQueue::STATUS_FAILED])
            ->where('attempts', '<', 3)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            $this->info('SMS queue is empty.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($jobs as $job) {
            $job->increment('attempts');

            if (Sms::sendOne($job)) {
                $sent++;
            }
        }

        $this->info("Delivered {$sent} of {$jobs->count()} SMS message(s).");

        return self::SUCCESS;
    }
}
