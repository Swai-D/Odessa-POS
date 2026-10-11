<?php

/*
 | Subscription plans. One codebase serves every shop; the plan decides which features are switched on and
 | which limits apply. A plan can `inherit` another and only lists what it adds. `*` means every feature,
 | and a null limit means unlimited. Per-shop exceptions go in the tenant's settings under `plan_overrides`
 | (`features` to add, `limits` to replace), so a special deal never needs a code change.
 */
return [
    // Used when a shop has no plan, or one that is not listed. Least privilege on purpose.
    'default' => 'basic',

    // Older plan names that map onto a current plan (the seeded demo shop gets everything).
    // Days after the paid-until date during which the shop keeps working normally, with a warning.
    'grace_days' => 7,

    // Days before the paid-until date from which the renewal reminder is shown.
    'warn_days' => 7,

    // Trial length for newly provisioned shops; the separate grace period starts after this deadline.
    'trial_days' => 14,

    // While a shop is on trial it can use every feature of this plan, so it sees the profit and reports it would
    // get by paying. Limits still follow the plan the shop is assigned to.
    'trial_plan' => 'medium',

    // Shown on the "not in your plan" page so a shop knows who to call to upgrade. Set in .env.
    'contact' => [
        'phone' => env('PLAN_CONTACT_PHONE'),
        'email' => env('PLAN_CONTACT_EMAIL'),
    ],

    'aliases' => ['demo' => 'enterprise'],

    // Every feature a plan can switch on. Everything not listed here (POS, products, stock, sales,
    // customers, settings...) is part of every plan.
    'features' => ['returns', 'credit_sales', 'brands', 'purchasing', 'printer', 'reports', 'expenses', 'mobile_money', 'fiscal', 'priority_support', 'custom_roles'],

    'plans' => [
        'basic' => [
            'features' => ['returns', 'credit_sales', 'printer'],
            'limits' => ['users' => 3, 'warehouses' => 1],
        ],
        'medium' => [
            'inherits' => 'basic',
            'features' => ['brands', 'purchasing', 'reports', 'expenses', 'mobile_money'],
            'limits' => ['users' => 10, 'warehouses' => 3],
        ],
        'enterprise' => [
            'inherits' => 'medium',
            'features' => ['*'],
            'limits' => ['users' => null, 'warehouses' => null],
        ],
    ],
];
