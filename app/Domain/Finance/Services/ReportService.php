<?php

namespace App\Domain\Finance\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only figures for the Reports page. Everything goes through the tenant-scoped models, so a shop only
 * ever sees its own records. Money is in minor units; periods are inclusive whole days.
 */
class ReportService
{
    private Carbon $from;

    private Carbon $to;

    public function __construct(Carbon $from, Carbon $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** @return array{count: int, gross: int, discounts: int, tax: int, returns: int, net: int, paid: int, credit: int} */
    public function salesSummary(): array
    {
        $sales = Sale::query()->whereBetween('sold_at', [$this->from, $this->to]);

        $total = (int) (clone $sales)->sum('total');
        $returns = (int) SaleReturn::query()->whereBetween('returned_at', [$this->from, $this->to])->sum('total');

        return [
            'count' => (clone $sales)->count(),
            'gross' => $total,
            'discounts' => (int) (clone $sales)->sum('discount_total'),
            'tax' => (int) (clone $sales)->sum('tax_total'),
            'returns' => $returns,
            'net' => $total - $returns,
            'paid' => (int) (clone $sales)->sum('amount_paid'),
            'credit' => (int) (clone $sales)->sum('balance_due'),
        ];
    }

    /** @return list<array{date: string, count: int, total: int}> */
    public function salesByDay(): array
    {
        $sales = Sale::query()->whereBetween('sold_at', [$this->from, $this->to])->get(['sold_at', 'total']);

        $rows = [];
        for ($day = $this->from->copy(); $day->lessThanOrEqualTo($this->to); $day->addDay()) {
            $daySales = $sales->filter(fn (Sale $sale): bool => $sale->sold_at->isSameDay($day));
            $rows[] = ['date' => $day->format('Y-m-d'), 'count' => $daySales->count(), 'total' => (int) $daySales->sum('total')];
        }

        return $rows;
    }

    /**
     * Best sellers by net revenue. Revenue and profit exclude tax and are net of returns; profit uses the cost
     * price recorded on the sale line at the time of sale.
     *
     * @return list<array{name: string, quantity: float, revenue: int, profit: int}>
     */
    public function topProducts(int $limit = 10): array
    {
        $net = '(sale_items.total - sale_items.returned_amount)';
        $revenue = "({$net} - sale_items.tax * {$net} * 1.0 / NULLIF(sale_items.total, 0))";
        $cost = '(sale_items.cost_price * (sale_items.quantity - sale_items.returned_quantity))';

        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [$this->from, $this->to])
            ->groupBy('sale_items.product_name', 'sale_items.sku')
            ->selectRaw('sale_items.product_name as name, SUM(sale_items.quantity - sale_items.returned_quantity) as quantity')
            ->selectRaw("SUM({$revenue}) as revenue")
            ->selectRaw("SUM({$revenue} - {$cost}) as profit")
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn (SaleItem $row): array => [
                'name' => (string) $row->getAttribute('name'),
                'quantity' => (float) $row->getAttribute('quantity'),
                'revenue' => (int) round((float) $row->getAttribute('revenue')),
                'profit' => (int) round((float) $row->getAttribute('profit')),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{method: string, amount: int}> */
    public function paymentsByMethod(): array
    {
        return Payment::query()
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->groupBy('method')
            ->selectRaw('method, SUM(amount) as amount')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (Payment $row): array => ['method' => $row->method, 'amount' => (int) $row->getAttribute('amount')])
            ->values()
            ->all();
    }

    /**
     * What customers owe right now (not limited to the period).
     *
     * @return list<array{name: string, balance: int}>
     */
    public function customerBalances(int $limit = 15): array
    {
        return Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.balance_due', '>', 0)
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.name as name, SUM(sales.balance_due) as balance')
            ->orderByDesc('balance')
            ->limit($limit)
            ->get()
            ->map(fn (Sale $row): array => ['name' => (string) $row->getAttribute('name'), 'balance' => (int) $row->getAttribute('balance')])
            ->values()
            ->all();
    }

    /** Stock on hand valued at cost price, right now. @return array{value: int, units: float, products: int} */
    public function stockValuation(): array
    {
        $row = ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_stock', true)
            ->selectRaw('COALESCE(SUM(product_stocks.quantity * products.cost_price), 0) as value')
            ->selectRaw('COALESCE(SUM(product_stocks.quantity), 0) as units')
            ->selectRaw('COUNT(DISTINCT products.id) as products')
            ->first();

        return [
            'value' => (int) round((float) $row?->getAttribute('value')),
            'units' => (float) $row?->getAttribute('units'),
            'products' => (int) $row?->getAttribute('products'),
        ];
    }

    /** @return Collection<int, Product> */
    public function lowStock(int $limit = 15): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where('alert_quantity', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(ps.quantity) FROM product_stocks ps WHERE ps.product_id = products.id), 0) <= products.alert_quantity')
            ->withSum('stocks as on_hand', 'quantity')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /** @return array{count: int, total: int, payable: int} */
    public function purchases(): array
    {
        $purchases = Purchase::query()->whereBetween('purchased_at', [$this->from, $this->to]);

        return [
            'count' => (clone $purchases)->count(),
            'total' => (int) (clone $purchases)->sum('total'),
            'payable' => (int) Purchase::query()->where('balance_due', '>', 0)->sum('balance_due'),
        ];
    }
}
