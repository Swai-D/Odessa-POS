<?php

/*
 | Where shops send their subscription payments. These are the platform's own receiving numbers, set in .env;
 | a method with no number is simply not shown, so nothing is invented. The USSD codes are the networks'
 | main menus (the wording of the menu items below them can change, so the page describes steps in general terms).
 */
return [
    'account_name' => env('BILLING_ACCOUNT_NAME', 'Odessa Lab Technologies'),

    // WhatsApp number in international format without "+", e.g. 255712345678. Optional.
    'whatsapp' => env('BILLING_WHATSAPP'),

    // The business ("lipa") number of each mobile-money network.
    'mobile' => [
        'mpesa' => ['label' => 'M-Pesa', 'network' => 'Vodacom', 'ussd' => '*150*00#', 'number' => env('BILLING_MPESA_NUMBER')],
        'mixx' => ['label' => 'Mixx by Yas', 'network' => 'Yas (Tigo Pesa)', 'ussd' => '*150*01#', 'number' => env('BILLING_MIXX_NUMBER')],
        'airtel' => ['label' => 'Airtel Money', 'network' => 'Airtel', 'ussd' => '*150*60#', 'number' => env('BILLING_AIRTEL_NUMBER')],
        'halopesa' => ['label' => 'HaloPesa', 'network' => 'Halotel', 'ussd' => '*150*88#', 'number' => env('BILLING_HALOPESA_NUMBER')],
    ],

    'bank' => [
        'name' => env('BILLING_BANK_NAME'),
        'account' => env('BILLING_BANK_ACCOUNT'),
        'branch' => env('BILLING_BANK_BRANCH'),
    ],
];
