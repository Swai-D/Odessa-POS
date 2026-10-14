<?php

return [
    'greeting' => 'Hello,',
    'salutation' => 'Thank you for using :app.',
    'phone_invalid' => 'Enter a valid Tanzanian mobile number, for example 0712 345 678.',

    'reminder_before' => [
        'subject' => 'Your subscription ends in :days days',
        'body' => 'The subscription for :shop ends on :date (in :days days). Renew before then so selling is never interrupted.',
        'sms' => ':shop: your subscription ends on :date (:days days). Please renew in time.',
    ],
    'reminder_today' => [
        'subject' => 'Your subscription ends today',
        'body' => 'The subscription for :shop ends today (:date). After the grace period of :grace days the shop becomes read-only. Your data is never deleted.',
        'sms' => ':shop: your subscription ends today. Please renew to keep selling.',
    ],
    'reminder_after' => [
        'subject' => 'Your subscription has ended',
        'body' => 'The subscription for :shop ended on :date. You are in the grace period and can still sell. After :grace days the shop becomes read-only. Your data is never deleted.',
        'sms' => ':shop: your subscription ended on :date. Renew soon to avoid read-only mode.',
    ],
    'payment_received' => [
        'subject' => 'Payment received, thank you',
        'body' => 'We received :amount for :shop. Your subscription is now paid until :date.',
        'sms' => ':shop: we received :amount. Paid until :date. Thank you.',
    ],
    'notice_received' => [
        'subject' => ':shop says it has paid',
        'body' => ':shop sent a payment notice of :amount (transaction :reference). Please confirm it and renew the subscription.',
        'sms' => ':shop paid :amount, ref :reference. Please confirm.',
    ],
];
