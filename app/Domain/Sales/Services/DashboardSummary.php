<?php

namespace App\Domain\Sales\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Models\Expense;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** The numbers on the dashboard, all read from the current shop's own records (minor units for money). */
class DashboardSummary
{
    public function __construct(private readonly ?Carbon $now = null) {}

    /** @return array{sales_count: int, sales_total: int, returns_total: int, credit_given: int} */
    public function today(): array
    {
        $now = $this->now ?? Carbon::now();
        $range = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

        $sales = Sale::query()->whereBetween('sold_at', $range);

        return [
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (int) (clone $sales)->sum('total'),
            'credit_given' => (int) (clone $sales)->sum('balance_due'),
            'returns_total' => (int) SaleReturn::query()->whereBetween('returned_at', $range)->sum('total'),
        ];
    }

    /** Expenses recorded for the current calendar month, in minor units. */
    public function expensesThisMonth(): int
    {
        $now = $this->now ?? Carbon::now();

        return (int) Expense::query()
            ->whereBetween('spent_on', [$now->copy()->startOfMonth()->format('Y-m-d'), $now->copy()->endOfMonth()->format('Y-m-d')])
            ->sum('amount');
    }

    /** Net sales for the current calendar month, after returns. */
    public function salesThisMonth(): int
    {
        $now = $this->now ?? Carbon::now();
        $range = [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfMonth()->endOfDay()];

        return (int) Sale::query()->whereBetween('sold_at', $range)->sum('total')
            - (int) SaleReturn::query()->whereBetween('returned_at', $range)->sum('total');
    }

    public function activeProductCount(): int
    {
        return Product::query()->where('is_active', true)->where('type', Product::TYPE_STANDARD)->count();
    }

    /** Unpaid customer balances across all sales. */
    public function outstandingCredit(): int
    {
        return (int) Sale::query()->where('balance_due', '>', 0)->sum('balance_due');
    }

    /**
     * Sales per day for the last $days days, oldest first.
     *
     * @return list<array{date: string, total: int, count: int}>
     */
    public function lastDays(int $days = 7): array
    {
        $now = $this->now ?? Carbon::now();
        $from = $now->copy()->subDays($days - 1)->startOfDay();

        $sales = Sale::query()->where('sold_at', '>=', $from)->where('sold_at', '<=', $now->copy()->endOfDay())->get(['sold_at', 'total']);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $daySales = $sales->filter(fn (Sale $sale): bool => $sale->sold_at->isSameDay($day));
            $series[] = ['date' => $day->format('Y-m-d'), 'total' => (int) $daySales->sum('total'), 'count' => $daySales->count()];
        }

        return $series;
    }

    /** @return Collection<int, Sale> */
    public function recentSales(int $limit = 8): Collection
    {
        return Sale::query()->with('customer')->latest('sold_at')->latest('id')->limit($limit)->get();
    }

    /**
     * Stock-tracked products at or below their alert quantity, filtered in SQL so it stays cheap on every page.
     *
     * @return Collection<int, Product>
     */
    public function lowStock(int $limit = 8): Collection
    {
        return $this->lowStockQuery()->withSum('stocks as on_hand', 'quantity')->orderBy('name')->limit($limit)->get();
    }

    public function lowStockCount(): int
    {
        return $this->lowStockQuery()->count();
    }

    /** @return Builder<Product> */
    private function lowStockQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->where('alert_quantity', '>', 0)
            ->whereRaw('COALESCE((SELECT SUM(ps.quantity) FROM product_stocks ps WHERE ps.product_id = products.id), 0) <= products.alert_quantity');
    }
}
