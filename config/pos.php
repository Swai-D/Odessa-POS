<?php

return [
    'default_currency' => env('POS_DEFAULT_CURRENCY', 'TZS'),
    'features' => [
        'printer' => false,
        'barcode_scanner' => false,
        'cash_drawer' => false,
        'tra_vfd' => false,
        'mobile_money' => false,
    ],
];
