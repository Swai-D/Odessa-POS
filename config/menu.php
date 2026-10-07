<?php

/*
 | Sidebar, grouped into sections like the original Dreams POS template (Main, Inventory, Stock, Sales...).
 | An item is shown only when the user holds its permission. `match` lists the route-name patterns for which
 | the item is highlighted, so detail and edit pages keep their section lit up.
 */
return [
    [
        'label' => 'app.menu.platform',
        'items' => [
            ['label' => 'app.menu.tenants', 'icon' => 'ti ti-building-store', 'route' => 'platform.tenants.index', 'permission' => null, 'platform' => true, 'match' => ['platform.*']],
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
            ['label' => 'app.menu.stock_adjustments', 'icon' => 'ti ti-stairs-up', 'route' => 'stock-adjustments.index', 'permission' => 'inventory.view', 'match' => ['stock-adjustments.*']],
        ],
    ],
    [
        'label' => 'app.menu.sales',
        'items' => [
            ['label' => 'app.menu.sales', 'icon' => 'ti ti-file-invoice', 'route' => 'sales.index', 'permission' => 'sales.view', 'match' => ['sales.*']],
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
        'label' => 'app.menu.reports',
        'items' => [
            ['label' => 'app.menu.reports', 'icon' => 'ti ti-chart-bar', 'route' => 'reports.index', 'feature' => 'reports', 'permission' => 'reports.view', 'match' => ['reports.*']],
        ],
    ],
    [
        'label' => 'app.menu.settings',
        'items' => [
            ['label' => 'app.menu.settings', 'icon' => 'ti ti-settings', 'route' => 'settings.index', 'permission' => 'settings.manage', 'match' => ['settings.index', 'settings.update']],
            ['label' => 'app.menu.integrations', 'icon' => 'ti ti-plug-connected', 'route' => 'settings.integrations', 'feature' => 'integrations', 'permission' => 'settings.manage', 'match' => ['settings.integrations*']],
        ],
    ],
];
