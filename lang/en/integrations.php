<?php

return [
    'title' => 'Integrations',
    'hint' => 'Plug in printers, fiscal receipts and mobile money. Each one stays off until you choose a provider and switch it on.',
    'enabled' => 'Enabled',
    'provider' => 'Provider',
    'no_providers' => 'No providers are available for this yet.',
    'secret_saved' => 'Saved. Leave blank to keep it.',
    'channels' => [
        'printer' => ['title' => 'Receipt printer', 'hint' => 'How receipts are printed at the till.'],
        'fiscal' => ['title' => 'TRA fiscal receipts', 'hint' => 'Send each sale to the tax authority.'],
        'payments' => ['title' => 'Mobile money', 'hint' => 'Let customers pay from their phone.'],
    ],
    'drivers' => [
        'browser' => 'Browser print (any printer)',
        'escpos' => 'Thermal printer (ESC/POS)',
    ],
    'fields' => [
        'connection' => 'Connection',
        'paper_width' => 'Paper width',
        'copies' => 'Copies',
        'cut' => 'Cut paper after printing',
    ],
    'options' => [
        'serial' => 'USB serial or paired Bluetooth',
        'usb' => 'USB (WebUSB)',
        'yes' => 'Yes',
        'no' => 'No',
    ],
    'print_thermal' => 'Print to thermal printer',
    'print_failed' => 'Could not print: :message',
    'print_unsupported' => 'This browser cannot talk to printers directly. Use Chrome or Edge, or switch to browser print in Settings.',
];
