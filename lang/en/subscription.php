<?php

return [
    'title' => 'Subscription',
    'states' => ['active' => 'Active', 'grace' => 'Grace period', 'readonly' => 'Read-only', 'suspended' => 'Suspended'],
    'suspended_title' => 'Shop switched off',
    'suspended_message' => 'This shop has been switched off. Please contact us.',
    'readonly_title' => 'Subscription expired',
    'readonly_message' => 'Your subscription has expired, so changes are switched off. You can still view and print your data. Renew to continue.',
    'readonly_banner' => 'Your subscription has expired. The shop is read-only until you renew. Your data is safe.',
    'grace_banner' => 'Your subscription has expired. Please renew by :date to avoid interruption.',
    'renew_banner' => 'Your subscription ends on :date (in :days days). Renew to avoid interruption.',
];
