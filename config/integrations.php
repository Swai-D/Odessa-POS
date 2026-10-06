<?php

use App\Domain\Integrations\Drivers\Printer\BrowserPrinterDriver;
use App\Domain\Integrations\Drivers\Printer\EscPosPrinterDriver;

/*
 | Optional channels a shop can plug in from Settings. Each channel lists the drivers it offers; a driver
 | declares its own configuration fields, so adding a provider is one class here and nothing else.
 | A channel stays inactive until its feature flag is on AND a driver has been chosen and saved.
 */
return [
    'channels' => [
        'printer' => [
            'feature' => 'printer',
            'drivers' => [BrowserPrinterDriver::class, EscPosPrinterDriver::class],
        ],
        // TRA fiscal receipts and mobile money: providers are added here once their specs are available.
        'fiscal' => [
            'feature' => 'tra_vfd',
            'drivers' => [],
        ],
        'payments' => [
            'feature' => 'mobile_money',
            'drivers' => [],
        ],
    ],
];
