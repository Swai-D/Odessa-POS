# UI Enhancements

This note records the dashboard, Reports, sidebar, and POS improvements delivered in October 2026. The purchased Dreams POS source and `public/assets/` template files remain outside these changes.

## Dashboard

The dashboard KPI order is net profit, gross profit, sales today, sales this month, low-stock alerts, active standard products, cost of goods sold, and monthly expenses. Profit and expense figures are shown only when the current plan includes expenses and the user has `expenses.view`. Sales for the month are net of returns. Gross profit and cost of goods sold use sale-line cost snapshots and exclude tax, following the existing Reports calculations.

Low-stock alert count comes from the full tenant-scoped query, not the limited list of product names shown in the banner. Product count includes active standard products.

A seven-day ApexCharts area chart plots daily sales totals. The existing daily table remains available as a precise accessible alternative. Chart data is generated from the current tenant's sales and formatted in the tenant's currency.

## Reports

Reports retains the date range form, summary metrics, tables, CSV exports, and existing plan/permission gates. Its Summary tab now includes:

- A mixed chart of daily sales amount and transaction count for the selected date range.
- A payment-method donut chart when payment data exists; otherwise the existing no-data state is shown.

The Profit & Loss tab adds a horizontal comparison chart for revenue, cost of goods sold, expenses, gross profit, and net profit. It uses the same plan and `expenses.view` checks as the underlying P&L figures. Horizontal bars preserve long labels on narrow screens. Existing P&L totals and expense-category breakdown remain visible.

The charts use ApexCharts already bundled with the app. Runtime data comes from tenant-scoped report services; no demo values or template-owned JavaScript were added. Empty states, translated labels, and selected report dates are preserved.

## Sidebar

The active route now marks both the sidebar link and its parent list item. The Dreams POS stylesheet styles the parent list item for the visible active background, while route match patterns continue to highlight index, detail, and edit pages.

## POS

- Product search matches case-insensitively across partial product names, SKUs, and barcodes; exact barcode/SKU results rank first. Older asynchronous search responses cannot replace newer results.
- Product tile clicks are handled by the POS before the template's delegated handler. This prevents the template from toggling the cart back to its empty state after the first item is added.
- Cart visibility is explicitly toggled between the empty-cart message and the order table.
- Customer lookup shows clickable name/phone suggestions, loading and no-results states, ignores stale requests, preserves selected customers, and closes suggestions when cleared or clicking elsewhere.
- Discount modal and summary element IDs are unique so Bootstrap opens the correct modal. Existing discounts are prefilled when editing; invalid values and percentages above 100 are explained without silently clearing the current discount. Fixed amounts accept decimal currency values.

## Verification

Focused verification used:

```sh
vendor/bin/pest tests/Feature/Sales/CheckoutTest.php tests/Feature/Sales/DashboardTest.php tests/Feature/Finance/ReportTest.php tests/Feature/Finance/ExpenseTest.php
node --check public/js/analytics-charts.js
vendor/bin/pint --test tests/Feature/Sales/DashboardTest.php tests/Feature/Finance/ReportTest.php tests/Feature/Finance/ExpenseTest.php
vendor/bin/phpstan analyse --level=5 --no-progress
```

The focused feature run passed 42 tests and 203 assertions. The chart renderer was also checked in the browser on dashboard and Reports at desktop and mobile widths, including opening the P&L tab.
