<?php

return [
    'callback_title' => 'Anwani yako ya callback ya AzamPay',
    'callback_hint' => 'Mpe AzamPay anwani hii kama callback URL ya app yako (sandbox: unaposajili app; production: pamoja na KYC). Bila hiyo, malipo hayawezi kuthibitishwa.',
    'errors' => [
        'not_configured' => 'Pesa ya simu haijawekwa. Unganisha AzamPay kwenye Mipangilio, Integrations.',
        'amount' => 'Kiasi lazima kiwe shilingi kamili, kuanzia 1 hadi :max.',
        'not_paid' => 'Malipo hayo ya pesa ya simu hayajathibitishwa, yameshatumika, au hayalingani na kiasi.',
    ],
    'statuses' => [
        'pending' => 'Tunasubiri mteja aidhinishe kwenye simu yake',
        'succeeded' => 'Imelipwa',
        'failed' => 'Malipo yameshindwa',
        'expired' => 'Mteja hakuidhinisha kwa wakati',
    ],
];
