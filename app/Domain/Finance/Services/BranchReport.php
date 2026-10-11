<?php

namespace App\Domain\Finance\Services;

use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Support\Carbon;

/**
 * Side-by-side figures for each branch (warehouse) of the shop over a period. A sale belongs to the warehouse it
 * was made from. Expenses are not tied to a warehouse, so only gross profit is shown per branch; money is in minor
 * units and every query goes through the tenant-scoped models.
 */
class BranchReport
{
    private Carbon $from;

    private Carbon $to;

    public function __construct(Carbon $from, Carbon $to)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /**
     * One row per branch that exists or had sales in the period, best net sales first.
     *
     * @return list<array{id: int, name: string, code: string, active: bool, sales_count: int, gross: int, returns: int, net: int, average: int, gross_profit: int, purchases: int, stock_value: int, stock_units: float, share: float}>
     */
    public function rows(): array
    {
        $sales = Sale::query()
            ->whereBetween('sold_at', [$this->from, $this->to])
            ->groupBy('sales.warehouse_id')
            ->selectRaw('sales.warehouse_id as branch, COUNT(*) as sales_count, COALESCE(SUM(sales.total), 0) as gross')
            ->get()->keyBy(fn (Sale $row): int => (int) $row->getAttribute('branch'));

        $returns = SaleReturn::query()
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->whereBetween('sale_returns.returned_at', [$this->from, $this->to])
            ->groupBy('sales.warehouse_id')
            ->selectRaw('sales.warehouse_id as branch, COALESCE(SUM(sale_returns.total), 0) as amount')
            ->get()->keyBy(fn (SaleReturn $row): int => (int) $row->getAttribute('branch'));

        [$revenueSql, $costSql] = ReportService::lineExpressions();
        $profit = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [$this->from, $this->to])
            ->groupBy('sales.warehouse_id')
            ->selectRaw("sales.warehouse_id as branch, COALESCE(SUM({$revenueSql} - {$costSql}), 0) as profit")
            ->get()->keyBy(fn (SaleItem $row): int => (int) $row->getAttribute('branch'));

        $purchases = Purchase::query()
            ->whereBetween('purchased_at', [$this->from, $this->to])
            ->groupBy('purchases.warehouse_id')
            ->selectRaw('purchases.warehouse_id as branch, COALESCE(SUM(purchases.total), 0) as amount')
            ->get()->keyBy(fn (Purchase $row): int => (int) $row->getAttribute('branch'));

        $stock = ProductStock::query()
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_stock', true)
            ->groupBy('product_stocks.warehouse_id')
            ->selectRaw('product_stocks.warehouse_id as branch, COALESCE(SUM(product_stocks.quantity), 0) as units')
            ->get()->keyBy(fn (ProductStock $row): int => (int) $row->getAttribute('branch'));

        // Stock is valued at what each batch cost, not at the product's current cost price.
        $stockValue = StockBatch::query()
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->where('products.track_stock', true)
            ->groupBy('stock_batches.warehouse_id')
            ->selectRaw('stock_batches.warehouse_id as branch, COALESCE(SUM(stock_batches.quantity_remaining * stock_batches.unit_cost), 0) as value')
            ->get()->keyBy(fn (StockBatch $row): int => (int) $row->getAttribute('branch'));

        $rows = [];
        foreach (Warehouse::query()->orderBy('name')->get() as $warehouse) {
            $id = (int) $warehouse->getKey();
            $count = (int) ($sales->get($id)?->getAttribute('sales_count') ?? 0);
            $gross = (int) ($sales->get($id)?->getAttribute('gross') ?? 0);
            $refunded = (int) ($returns->get($id)?->getAttribute('amount') ?? 0);

            // An inactive branch with nothing to show would only add noise.
            if (! $warehouse->is_active && $count === 0 && $stock->get($id) === null) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'name' => (string) $warehouse->name,
                'code' => (string) $warehouse->code,
                'active' => (bool) $warehouse->is_active,
                'sales_count' => $count,
                'gross' => $gross,
                'returns' => $refunded,
                'net' => $gross - $refunded,
                'average' => $count > 0 ? intdiv($gross, $count) : 0,
                'gross_profit' => (int) round((float) ($profit->get($id)?->getAttribute('profit') ?? 0)),
                'purchases' => (int) ($purchases->get($id)?->getAttribute('amount') ?? 0),
                'stock_value' => (int) round((float) ($stockValue->get($id)?->getAttribute('value') ?? 0)),
                'stock_units' => (float) ($stock->get($id)?->getAttribute('units') ?? 0),
                'share' => 0.0,
            ];
        }

        $total = array_sum(array_column($rows, 'net'));
        foreach ($rows as &$row) {
            $row['share'] = $total > 0 ? round($row['net'] * 100 / $total, 1) : 0.0;
        }
        unset($row);

        usort($rows, fn (array $a, array $b): int => [$b['net'], $a['name']] <=> [$a['net'], $b['name']]);

        return $rows;
    }

    /**
     * @param  list<array{sales_count: int, gross: int, returns: int, net: int, gross_profit: int, purchases: int, stock_value: int, stock_units: float}>  $rows
     * @return array{sales_count: int, gross: int, returns: int, net: int, gross_profit: int, purchases: int, stock_value: int, stock_units: float}
     */
    public static function totals(array $rows): array
    {
        $sum = fn (string $key): int|float => array_sum(array_column($rows, $key));

        return [
            'sales_count' => (int) $sum('sales_count'),
            'gross' => (int) $sum('gross'),
            'returns' => (int) $sum('returns'),
            'net' => (int) $sum('net'),
            'gross_profit' => (int) $sum('gross_profit'),
            'purchases' => (int) $sum('purchases'),
            'stock_value' => (int) $sum('stock_value'),
            'stock_units' => (float) $sum('stock_units'),
        ];
    }
}
