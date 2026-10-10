<?php

/*
 | Sidebar, grouped into sections like the original Dreams POS template (Main, Inventory, Stock, Sales...).
 | An item is shown only when the user holds its permission (or any one of them, when a list is given). `match` lists the route-name patterns for which
 | the item is highlighted, so detail and edit pages keep their section lit up.
 */
return [
    [
        'label' => 'app.menu.platform',
        'items' => [
            ['label' => 'app.menu.dashboard', 'icon' => 'ti ti-layout-dashboard', 'route' => 'platform.dashboard', 'permission' => null, 'platform' => true, 'match' => ['platform.dashboard']],
            ['label' => 'app.menu.tenants', 'icon' => 'ti ti-building-store', 'route' => 'platform.tenants.index', 'permission' => null, 'platform' => true, 'match' => ['platform.tenants.*']],
            ['label' => 'app.menu.plans', 'icon' => 'ti ti-packages', 'route' => 'platform.plans.index', 'permission' => null, 'platform' => true, 'match' => ['platform.plans.*']],
            ['label' => 'app.menu.platform_transactions', 'icon' => 'ti ti-receipt-2', 'route' => 'platform.payments.index', 'permission' => null, 'platform' => true, 'match' => ['platform.payments.*']],
        ],
    ],
    [
        'label' => 'app.menu.main',
        'items' => [
            ['label' => 'app.menu.dashboard', 'icon' => 'ti ti-layout-grid', 'route' => 'dashboard', 'permission' => 'dashboard.view', 'match' => ['dashboard']],
            ['label' => 'app.menu.pos', 'icon' => 'ti ti-device-laptop', 'route' => 'pos.index', 'permission' => 'pos.access', 'match' => ['pos.*']],
        ],
    ],
    [
        'label' => 'app.menu.inventory',
        'items' => [
            ['label' => 'app.menu.products', 'icon' => 'ti ti-box', 'route' => 'products.index', 'permission' => 'products.view', 'match' => ['products.*']],
            ['label' => 'app.menu.categories', 'icon' => 'ti ti-list-details', 'route' => 'categories.index', 'permission' => 'categories.view', 'match' => ['categories.*']],
            ['label' => 'app.menu.brands', 'icon' => 'ti ti-triangles', 'route' => 'brands.index', 'feature' => 'brands', 'permission' => 'brands.view', 'match' => ['brands.*']],
            ['label' => 'app.menu.units', 'icon' => 'ti ti-brand-unity', 'route' => 'units.index', 'permission' => 'units.view', 'match' => ['units.*']],
            ['label' => 'app.menu.warehouses', 'icon' => 'ti ti-building-warehouse', 'route' => 'warehouses.index', 'permission' => 'warehouses.view', 'match' => ['warehouses.*']],
        ],
    ],
    [
        'label' => 'app.menu.stock',
        'items' => [
            ['label' => 'app.menu.stock_levels', 'icon' => 'ti ti-stack-3', 'route' => 'stock.index', 'permission' => 'inventory.view', 'match' => ['stock.*']],
            ['label' => 'app.menu.stock_transfers', 'icon' => 'ti ti-arrows-exchange', 'route' => 'stock-transfers.index', 'permission' => 'inventory.view', 'match' => ['stock-transfers.*']],
            ['label' => 'app.menu.stock_adjustments', 'icon' => 'ti ti-stairs-up', 'route' => 'stock-adjustments.index', 'permission' => 'inventory.view', 'match' => ['stock-adjustments.*']],
        ],
    ],
    [
        'label' => 'app.menu.sales',
        'items' => [
            ['label' => 'app.menu.sales', 'icon' => 'ti ti-file-invoice', 'route' => 'sales.index', 'permission' => 'sales.view', 'match' => ['sales.*']],
            ['label' => 'app.menu.till', 'icon' => 'ti ti-cash', 'route' => 'till-closings.index', 'permission' => ['till.close', 'till.view'], 'match' => ['till-closings.*']],
        ],
    ],
    [
        'label' => 'app.menu.purchases',
        'items' => [
            ['label' => 'app.menu.purchases', 'icon' => 'ti ti-truck-delivery', 'route' => 'purchases.index', 'feature' => 'purchasing', 'permission' => 'purchases.view', 'match' => ['purchases.*']],
        ],
    ],
    [
        'label' => 'app.menu.peoples',
        'items' => [
            ['label' => 'app.menu.customers', 'icon' => 'ti ti-users', 'route' => 'customers.index', 'permission' => 'customers.view', 'match' => ['customers.*']],
            ['label' => 'app.menu.suppliers', 'icon' => 'ti ti-building-store', 'route' => 'suppliers.index', 'feature' => 'purchasing', 'permission' => 'suppliers.view', 'match' => ['suppliers.*']],
        ],
    ],
    [
        'label' => 'app.menu.finance',
        'items' => [
            ['label' => 'app.menu.expenses', 'icon' => 'ti ti-receipt-2', 'route' => 'expenses.index', 'feature' => 'expenses', 'permission' => 'expenses.view', 'match' => ['expenses.*']],
            ['label' => 'app.menu.expense_categories', 'icon' => 'ti ti-category', 'route' => 'expense-categories.index', 'feature' => 'expenses', 'permission' => 'expenses.view', 'match' => ['expense-categories.*']],
        ],
    ],
    [
        'label' => 'app.menu.reports',
        'items' => [
            ['label' => 'app.menu.reports', 'icon' => 'ti ti-chart-bar', 'route' => 'reports.index', 'feature' => 'reports', 'permission' => 'reports.view', 'match' => ['reports.*']],
        ],
    ],
    [
        'label' => 'app.menu.settings',
        'items' => [
            ['label' => 'app.menu.settings', 'icon' => 'ti ti-settings', 'route' => 'settings.index', 'permission' => 'settings.manage', 'match' => ['settings.index', 'settings.update']],
            ['label' => 'app.menu.users', 'icon' => 'ti ti-users-group', 'route' => 'users.index', 'permission' => 'settings.manage', 'match' => ['users.*']],
            ['label' => 'app.menu.integrations', 'icon' => 'ti ti-plug-connected', 'route' => 'settings.integrations', 'feature' => 'integrations', 'permission' => 'settings.manage', 'match' => ['settings.integrations*']],
        ],
    ],
];
