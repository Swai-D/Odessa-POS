<?php

return [
    ['label' => 'app.menu.dashboard', 'icon' => 'ti ti-layout-dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
    ['label' => 'app.menu.pos', 'icon' => 'ti ti-device-desktop', 'route' => 'pos.index', 'permission' => 'pos.access'],
    [
        'label' => 'app.menu.inventory',
        'icon' => 'ti ti-package',
        'children' => [
            ['label' => 'app.menu.products', 'route' => 'products.index', 'permission' => 'products.view'],
            ['label' => 'app.menu.categories', 'route' => 'categories.index', 'permission' => 'categories.view'],
            ['label' => 'app.menu.brands', 'route' => 'brands.index', 'permission' => 'brands.view'],
            ['label' => 'app.menu.units', 'route' => 'units.index', 'permission' => 'units.view'],
            ['label' => 'app.menu.warehouses', 'route' => 'warehouses.index', 'permission' => 'warehouses.view'],
            ['label' => 'app.menu.stock_levels', 'route' => 'stock.index', 'permission' => 'inventory.view'],
            ['label' => 'app.menu.stock_adjustments', 'route' => 'stock-adjustments.index', 'permission' => 'inventory.view'],
        ],
    ],
    ['label' => 'app.menu.sales', 'icon' => 'ti ti-shopping-cart', 'route' => 'sales.index', 'permission' => 'sales.view'],
    ['label' => 'app.menu.purchases', 'icon' => 'ti ti-truck-delivery', 'route' => 'purchases.index', 'permission' => 'purchases.view'],
    ['label' => 'app.menu.people', 'icon' => 'ti ti-users', 'route' => 'people.index', 'permission' => 'people.view'],
    ['label' => 'app.menu.reports', 'icon' => 'ti ti-chart-bar', 'route' => 'reports.index', 'permission' => 'reports.view'],
    ['label' => 'app.menu.settings', 'icon' => 'ti ti-settings', 'route' => 'settings.index', 'permission' => 'settings.manage'],
];
