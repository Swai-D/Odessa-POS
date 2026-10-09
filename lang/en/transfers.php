<?php

return [
    'title' => 'Stock Transfers',
    'subtitle' => 'Move stock from one warehouse to another',
    'new' => 'New transfer',
    'number' => 'Number',
    'date' => 'Date',
    'from' => 'From warehouse',
    'to' => 'To warehouse',
    'lines' => 'Products',
    'by' => 'Moved by',
    'note' => 'Note',
    'product' => 'Product',
    'qty' => 'Quantity',
    'add_line' => 'Add product',
    'save' => 'Move stock',
    'none' => 'No transfers yet.',
    'done' => 'Transfer :number recorded.',
    'need_two' => 'You need at least two active warehouses to move stock between them. Add one under Warehouses.',
    'errors' => [
        'same_warehouse' => 'Choose two different warehouses.',
        'warehouse_unavailable' => 'Both warehouses must exist and be active.',
        'product_unavailable' => 'One of the products is not available for transfer.',
        'whole_quantity' => ':product can only be moved in whole units.',
        'not_enough' => 'Not enough :product in :warehouse.',
    ],
];
