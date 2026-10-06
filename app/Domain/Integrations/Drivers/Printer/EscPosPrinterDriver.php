<?php

namespace App\Domain\Integrations\Drivers\Printer;

use App\Domain\Integrations\Contracts\Driver;

/**
 * Raw ESC/POS thermal printing. The server builds the receipt bytes and the cashier's browser sends them
 * straight to a USB or serial/Bluetooth-paired printer (Web USB / Web Serial), so it also works when
 * the server is in the cloud and the printer is in the shop.
 */
class EscPosPrinterDriver implements Driver
{
    public static function key(): string
    {
        return 'escpos';
    }

    public static function label(): string
    {
        return 'integrations.drivers.escpos';
    }

    public static function fields(): array
    {
        return [
            [
                'name' => 'connection', 'label' => 'integrations.fields.connection', 'type' => 'select', 'required' => true,
                'options' => ['serial' => 'integrations.options.serial', 'usb' => 'integrations.options.usb'],
                'default' => 'serial',
            ],
            [
                'name' => 'paper_width', 'label' => 'integrations.fields.paper_width', 'type' => 'select', 'required' => true,
                'options' => ['58' => '58 mm', '80' => '80 mm'], 'default' => '80',
            ],
            ['name' => 'copies', 'label' => 'integrations.fields.copies', 'type' => 'number', 'required' => true, 'default' => '1'],
            ['name' => 'cut', 'label' => 'integrations.fields.cut', 'type' => 'select', 'required' => true,
                'options' => ['1' => 'integrations.options.yes', '0' => 'integrations.options.no'], 'default' => '1'],
        ];
    }
}
