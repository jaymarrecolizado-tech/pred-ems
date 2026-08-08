<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base HRIS notification: delivered to the in-system inbox (database), email
 * (when mail is configured AND the admin's email toggle is on), and SMS
 * (enqueued for the Android gateway when enabled and the recipient has a
 * phone on file). SMS is soft-fail — it is queued asynchronously and never
 * blocks the action that raised the event.
 */
abstract class HrisNotification extends Notification
{
    public function __construct(
        protected string $title,
        protected string $body,
        protected string $url = '',
        protected ?string $smsText = null,
    ) {}

    public function via($notifiable): array
    {
        // The in-system inbox always receives the event; SMS and mail are
        // admin-switchable (settings page). SMS enqueues before mail so a
        // broken SMTP relay can never prevent the (cheap, local) SMS queue
        // insert.
        $channels = ['database', SmsChannel::class];

        if (Setting::emailNotificationsEnabled()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('DICT RO2 HRIS — ' . $this->title)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line($this->body);

        if ($this->url) {
            $mail->action('Open in HRIS', $this->url);
        }

        return $mail->line('This is a system notification from the DICT Regional Office 2 Human Resource Information System.');
    }

    public function toSms($notifiable): ?string
    {
        return $this->smsText
            ? mb_substr('DICT RO2 HRIS: ' . $this->smsText, 0, \App\Support\Sms::maxMessageLength())
            : null;
    }
}
