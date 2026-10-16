<?php

namespace App\Notifications;

/** Goes to the platform team: a shop proved ownership of its domain and the name must be attached to the hosting. */
class DomainVerifiedNotification extends ShopNotification
{
    public function __construct(public readonly string $shop, public readonly string $domain) {}

    protected function key(): string
    {
        return 'domain_verified';
    }

    protected function replacements(): array
    {
        return ['shop' => $this->shop, 'domain' => $this->domain];
    }
}
