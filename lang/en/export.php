<?php

return [
    'title' => 'Download your data',
    'hint' => 'Your data belongs to your shop. Download it any time as CSV files that open in Excel.',
    'back' => 'Back to Settings',
    'open' => 'Download your data',
    'open_hint' => 'Take a copy of your products, customers, sales, payments, expenses and stock.',
    'data' => 'Data',
    'contains' => 'What is in it',
    'note' => 'Amounts are in whole currency units (for example 1500.00). Each download is recorded in the audit log.',
    'sets' => [
        'products' => ['name' => 'Products', 'about' => 'SKU, barcode, name, category, prices, tax and whether active or deleted.'],
        'customers' => ['name' => 'Customers', 'about' => 'Contact details, credit limit and the amount each customer owes.'],
        'suppliers' => ['name' => 'Suppliers', 'about' => 'Contact details of your suppliers.'],
        'sales' => ['name' => 'Sales', 'about' => 'One row per sale: date, customer, totals, returns, paid and balance.'],
        'sale_items' => ['name' => 'Sale items', 'about' => 'The products on every sale, with quantity, price, discount and tax.'],
        'payments' => ['name' => 'Payments', 'about' => 'Every payment received on sales, by method.'],
        'expenses' => ['name' => 'Expenses', 'about' => 'Date, category, amount and method of each expense.'],
        'stock' => ['name' => 'Stock', 'about' => 'Current quantity of each product in each warehouse.'],
    ],
];
