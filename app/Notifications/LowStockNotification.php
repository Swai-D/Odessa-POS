<?php

namespace App\Notifications;

class LowStockNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly int $count, public readonly string $names) {}

    protected function key(): string
    {
        return 'low_stock';
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'count' => $this->count, 'names' => $this->names];
    }
}
