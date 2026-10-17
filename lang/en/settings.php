<?php

return [
    'title' => 'Settings',
    'hint' => 'Business details and optional features for this shop',
    'business' => 'Business',
    'business_name' => 'Business name',
    'currency' => 'Currency',
    'receipt_footer' => 'Receipt footer',
    'features' => 'Optional features',
    'features_hint' => 'Hardware and payment integrations stay off until you switch them on.',
    'feature_names' => [
        'printer' => 'Receipt printer',
        'barcode_scanner' => 'Barcode scanner',
        'cash_drawer' => 'Cash drawer',
        'tra_vfd' => 'TRA fiscal receipts (VFD/EFD)',
        'mobile_money' => 'Mobile money',
        'weighed_products' => 'Weighed products',
        'batch_tracking' => 'Batch tracking',
        'expiry_tracking' => 'Expiry tracking',
    ],
    'feature_hints' => [
        'barcode_scanner' => 'The till keeps the cursor in the search box, beeps when a scanned item is added and sounds an error when a code is not found. Without it, Enter only searches.',
        'cash_drawer' => 'A Open drawer button appears on the till, and the drawer opens when a sale includes cash. Needs a drawer connected to your ESC/POS receipt printer.',
        'weighed_products' => 'Lets you mark a product as sold by weight, so the till accepts decimal quantities such as 1.25 kg.',
        'batch_tracking' => 'Lets you track stock by batch on a product; sales take from the oldest batch first.',
        'expiry_tracking' => 'Lets you give a product batch an expiry date and see what expires soon.',
    ],
    'drawer_needs_printer' => 'Not active yet: set up the ESC/POS receipt printer in Integrations first.',
];
