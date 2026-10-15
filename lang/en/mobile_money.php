<?php

return [
    'callback_title' => 'Your AzamPay callback address',
    'callback_hint' => 'Give this address to AzamPay as the callback URL of your app (sandbox: when you register the app; production: with your KYC). Without it, payments cannot be confirmed.',
    'errors' => [
        'not_configured' => 'Mobile money is not set up. Connect AzamPay under Settings, Integrations.',
        'amount' => 'The amount must be whole shillings, from 1 up to :max.',
        'not_paid' => 'That mobile money payment is not confirmed, was already used, or does not match the amount.',
    ],
    'statuses' => [
        'pending' => 'Waiting for the customer to approve on their phone',
        'succeeded' => 'Paid',
        'failed' => 'Payment failed',
        'expired' => 'The customer did not approve in time',
    ],
];
