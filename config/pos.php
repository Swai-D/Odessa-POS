<?php

return [
    // Where uploaded files (product pictures) are kept: a disk from config/filesystems.php. `public` is the local
    // disk (a Railway volume mounted at storage/app/public); set UPLOADS_DISK=s3 for S3/R2 once it is configured.
    'uploads_disk' => env('UPLOADS_DISK', 'public'),
    'default_currency' => env('POS_DEFAULT_CURRENCY', 'TZS'),
    // Most rows one product CSV upload may hold (keeps the request short and the transaction small).
    'import_max_rows' => 2000,
    // Offline sales older than this many days are refused when the till syncs them.
    'offline_max_age_days' => 30,
    // Most products and customers the till downloads for offline use.
    'offline_max_products' => 5000,
    'offline_max_customers' => 1000,
    'currencies' => ['TZS', 'KES', 'UGX', 'USD', 'EUR'],
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
