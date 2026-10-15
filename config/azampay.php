<?php

/*
 | AzamPay endpoints. Each shop brings its own AzamPay app, so credentials are saved per shop under Settings -> Integrations;
 | only the hosts live here. Sandbox hosts come from AzamPay's API reference. The production hosts below must be
 | confirmed with AzamPay at go-live (override with the env variables if they differ).
 */
return [
    'environments' => [
        'sandbox' => [
            'auth' => env('AZAMPAY_SANDBOX_AUTH_URL', 'https://authenticator-sandbox.azampay.co.tz'),
            'api' => env('AZAMPAY_SANDBOX_API_URL', 'https://sandbox.azampay.co.tz'),
        ],
        'production' => [
            'auth' => env('AZAMPAY_PRODUCTION_AUTH_URL', 'https://authenticator.azampay.co.tz'),
            'api' => env('AZAMPAY_PRODUCTION_API_URL', 'https://checkout.azampay.co.tz'),
        ],
    ],

    'timeout' => 20,

    // How long a customer has to approve the prompt on their phone before the request is shown as expired.
    'request_ttl_minutes' => 5,

    // AzamPay's limit for one mobile money checkout, in whole shillings.
    'max_amount' => 5000000,
];
