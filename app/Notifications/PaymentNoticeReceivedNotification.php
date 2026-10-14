<?php

namespace App\Notifications;

/** Goes to the platform team, not the shop: a shop says it has paid and is waiting for confirmation. */
class PaymentNoticeReceivedNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly string $amount, public readonly string $reference) {}

    protected function key(): string
    {
        return 'notice_received';
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'amount' => $this->amount, 'reference' => $this->reference];
    }
}
