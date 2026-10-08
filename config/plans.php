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

    'aliases' => ['demo' => 'enterprise'],

    // Every feature a plan can switch on. Everything not listed here (POS, products, stock, sales,
    // customers, settings...) is part of every plan.
    'features' => ['returns', 'credit_sales', 'brands', 'purchasing', 'integrations', 'reports', 'expenses', 'fiscal'],

    'plans' => [
        'basic' => [
            'features' => ['returns', 'credit_sales'],
            'limits' => ['users' => 3, 'warehouses' => 1],
        ],
        'medium' => [
            'inherits' => 'basic',
            'features' => ['brands', 'purchasing', 'integrations', 'reports', 'expenses'],
            'limits' => ['users' => 10, 'warehouses' => 3],
        ],
        'enterprise' => [
            'inherits' => 'medium',
            'features' => ['*'],
            'limits' => ['users' => null, 'warehouses' => null],
        ],
    ],
];
