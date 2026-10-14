<?php

namespace App\Notifications;

use App\Support\Sms\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for messages sent by email and SMS. Subclasses hold only plain values (text, numbers), never models,
 * so the queued copy cannot leak or restore another shop's data. The wording comes from lang/*\/notifications.php
 * in the language chosen with ->locale().
 */
abstract class ShopNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    /** Lang key under "notifications." (without the prefix). */
    abstract protected function key(): string;

    /** @return array<string, string|int> */
    abstract protected function replacements(): array;

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'notifications.'.$this->key();

        return (new MailMessage)
            ->subject(__("{$key}.subject", $this->replacements()))
            ->greeting(__('notifications.greeting'))
            ->line(__("{$key}.body", $this->replacements()))
            ->salutation(__('notifications.salutation', ['app' => (string) config('app.name')]));
    }

    public function toSms(object $notifiable): string
    {
        return __('notifications.'.$this->key().'.sms', $this->replacements());
    }
}
