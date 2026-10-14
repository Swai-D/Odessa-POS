<?php

namespace App\Notifications;

class PaymentReceivedNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly string $amount, public readonly string $until) {}

    protected function key(): string
    {
        return 'payment_received';
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'amount' => $this->amount, 'date' => $this->until];
    }
}
