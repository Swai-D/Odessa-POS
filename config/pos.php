<?php

return [
    'default_currency' => env('POS_DEFAULT_CURRENCY', 'TZS'),
    'features' => [
        'printer' => false,
        'barcode_scanner' => false,
        'cash_drawer' => false,
        'tra_vfd' => false,
        'mobile_money' => false,
        // Optional product behaviours, switched on per tenant.
        'weighed_products' => false,
        'batch_tracking' => false,
        'expiry_tracking' => false,
    ],

    // Read by DatabaseSeeder through config() so seeding still works with `config:cache`.
    'seed' => [
        'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'admin@example.test'),
        'super_admin_password' => env('SUPER_ADMIN_PASSWORD'),
        'demo_owner_email' => env('DEMO_OWNER_EMAIL', 'owner@demo.test'),
        'demo_owner_password' => env('DEMO_OWNER_PASSWORD'),
    ],
];
