<?php

return [
    // Where platform-level alerts go (for example a shop sent a payment notice). Both are optional.
    'admin_email' => env('NOTIFY_ADMIN_EMAIL'),
    'admin_phone' => env('NOTIFY_ADMIN_PHONE'),

    'sms' => [
        // "nextsms" sends real messages, "log" only writes them to the log, "null" drops them.
        'driver' => env('SMS_DRIVER', 'log'),
        'country_code' => '255',
        'sender_id' => env('SMS_SENDER_ID', 'ODESSA'),
        'nextsms' => [
            'base_url' => env('NEXTSMS_BASE_URL', 'https://messaging-service.co.tz'),
            'send_path' => env('NEXTSMS_SEND_PATH', '/api/sms/v1/text/single'),
            'username' => env('NEXTSMS_USERNAME'),
            'password' => env('NEXTSMS_PASSWORD'),
            'timeout' => 15,
        ],
    ],

    // Days before the deadline to remind a shop (0 = on the day, negative = after it passed).
    'reminder_days' => [7, 3, 0],
];
