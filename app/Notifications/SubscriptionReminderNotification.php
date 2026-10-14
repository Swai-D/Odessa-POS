<?php

namespace App\Notifications;

class SubscriptionReminderNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly int $daysLeft, public readonly string $deadline) {}

    protected function key(): string
    {
        return match (true) {
            $this->daysLeft > 0 => 'reminder_before',
            $this->daysLeft === 0 => 'reminder_today',
            default => 'reminder_after',
        };
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'days' => abs($this->daysLeft), 'date' => $this->deadline, 'grace' => (int) config('plans.grace_days')];
    }
}
