<?php

namespace App\Domain\Integrations\Drivers\Printer;

use App\Domain\Integrations\Contracts\Driver;

/** Prints receipts through the browser's own print dialog. Works with any printer the computer can use. */
class BrowserPrinterDriver implements Driver
{
    public static function key(): string
    {
        return 'browser';
    }

    public static function label(): string
    {
        return 'integrations.drivers.browser';
    }

    public static function fields(): array
    {
        return [];
    }
}
