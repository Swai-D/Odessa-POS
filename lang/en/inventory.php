<?php

return [
    'fields' => [
        'code' => 'Code',
        'address' => 'Address',
        'phone' => 'Phone',
        'default' => 'Default',
        'date' => 'Date',
        'product' => 'Product',
        'warehouse' => 'Warehouse',
        'type' => 'Type',
        'quantity' => 'Quantity',
        'balance' => 'Balance',
        'reason' => 'Reason',
        'user' => 'By',
        'direction' => 'Direction',
    ],
    'types' => [
        'opening' => 'Opening stock',
        'adjustment_in' => 'Adjustment (add)',
        'adjustment_out' => 'Adjustment (remove)',
        'sale' => 'Sale',
        'purchase' => 'Purchase',
    ],
    'directions' => [
        'in' => 'Add stock',
        'out' => 'Remove stock',
    ],
    'stock_levels' => 'Stock Levels',
    'stock_levels_hint' => 'Current quantity per product and warehouse',
    'stock_adjustments' => 'Stock Adjustments',
    'stock_adjustments_hint' => 'Every stock change is recorded in this ledger',
    'new_adjustment' => 'New Adjustment',
    'opening_stock' => 'Opening stock',
    'insufficient_stock' => 'Not enough stock for ":product".',
    'cannot_delete_has_stock' => 'This warehouse has stock history and cannot be deleted.',
];
