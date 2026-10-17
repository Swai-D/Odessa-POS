<?php

namespace App\Notifications;

class ExpiryNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly int $count, public readonly int $expired, public readonly string $names) {}

    protected function key(): string
    {
        return 'expiry';
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'count' => $this->count, 'expired' => $this->expired, 'names' => $this->names];
    }
}
