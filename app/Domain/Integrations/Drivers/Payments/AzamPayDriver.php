<?php

namespace App\Domain\Integrations\Drivers\Payments;

use App\Domain\Integrations\Contracts\Driver;

/** Mobile money through the shop's own AzamPay app: the money goes straight to the shop's AzamPay account. */
class AzamPayDriver implements Driver
{
    public static function key(): string
    {
        return 'azampay';
    }

    public static function label(): string
    {
        return 'integrations.drivers.azampay';
    }

    public static function fields(): array
    {
        return [
            [
                'name' => 'environment', 'label' => 'integrations.fields.environment', 'type' => 'select', 'required' => true,
                'options' => ['sandbox' => 'integrations.options.sandbox', 'production' => 'integrations.options.production'],
                'default' => 'sandbox',
            ],
            ['name' => 'app_name', 'label' => 'integrations.fields.app_name', 'type' => 'text', 'required' => true],
            ['name' => 'client_id', 'label' => 'integrations.fields.client_id', 'type' => 'text', 'required' => true],
            ['name' => 'client_secret', 'label' => 'integrations.fields.client_secret', 'type' => 'password', 'required' => true, 'secret' => true],
            ['name' => 'api_key', 'label' => 'integrations.fields.api_key', 'type' => 'password', 'required' => true, 'secret' => true],
        ];
    }
}
