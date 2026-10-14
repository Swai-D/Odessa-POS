<?php

namespace App\Domain\Finance\Services;

use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use Illuminate\Support\Carbon;

/**
 * The figures of a business statement, taken from the shop's own records through the same ReportService the
 * Reports page uses, so the two can never disagree. The period is the last whole calendar months before today:
 * a month that is still running is never included. Money is in minor units.
 */
class BusinessStatementBuilder
{
    /**
     * @return array{from: Carbon, to: Carbon, data: array<string, mixed>}
     */
    public function handle(int $months, Carbon $today): array
    {
        $from = $today->copy()->startOfMonth()->subMonthsNoOverflow($months)->startOfDay();
        $to = $today->copy()->startOfMonth()->subDay()->endOfDay();

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $start = $from->copy()->addMonthsNoOverflow($i);
            $rows[] = $this->month($start, $start->copy()->endOfMonth());
        }

        $whole = new ReportService($from, $to);
        $sales = $whole->salesSummary();
        $pnl = $whole->profitAndLoss();

        $firstActive = null;
        foreach ($rows as $index => $row) {
            if ($row['sales_count'] > 0) {
                $firstActive = $index;
                break;
            }
        }
        $activeMonths = $firstActive === null ? 0 : $months - $firstActive;

        $customers = (int) Sale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->whereNotNull('customer_id')
            ->distinct()
            ->count('customer_id');

        $totals = [
            'sales_count' => $sales['count'],
            'gross_sales' => $sales['gross'],
            'returns' => $sales['returns'],
            'net_sales' => $sales['net'],
            'cost_of_goods' => $pnl['cost'],
            'gross_profit' => $pnl['gross_profit'],
            'expenses' => $pnl['expenses'],
            'net_profit' => $pnl['net_profit'],
            'purchases' => (int) $whole->purchases()['total'],
        ];

        return [
            'from' => $from,
            'to' => $to,
            'data' => [
                'months' => $rows,
                'totals' => $totals,
                'averages' => [
                    'active_months' => $activeMonths,
                    'monthly_net_sales' => $activeMonths > 0 ? intdiv($totals['net_sales'], $activeMonths) : 0,
                    'monthly_net_profit' => $activeMonths > 0 ? intdiv($totals['net_profit'], $activeMonths) : 0,
                    'average_sale' => $totals['sales_count'] > 0 ? intdiv($totals['gross_sales'], $totals['sales_count']) : 0,
                ],
                'activity' => [
                    'customers' => $customers,
                    'first_sale_on' => Sale::query()->min('sold_at') === null ? null : Carbon::parse((string) Sale::query()->min('sold_at'))->format('Y-m-d'),
                ],
                'position' => [
                    'receivables' => (int) Sale::query()->where('balance_due', '>', 0)->sum('balance_due'),
                    'payables' => (int) Purchase::query()->where('balance_due', '>', 0)->sum('balance_due'),
                    'stock_value' => (int) $whole->stockValuation()['value'],
                ],
                'expense_categories' => array_slice($pnl['by_category'], 0, 8),
            ],
        ];
    }

    /** @return array<string, int|string> */
    private function month(Carbon $start, Carbon $end): array
    {
        $report = new ReportService($start, $end);
        $sales = $report->salesSummary();
        $pnl = $report->profitAndLoss();

        return [
            'key' => $start->format('Y-m'),
            'sales_count' => $sales['count'],
            'gross_sales' => $sales['gross'],
            'returns' => $sales['returns'],
            'net_sales' => $sales['net'],
            'gross_profit' => $pnl['gross_profit'],
            'expenses' => $pnl['expenses'],
            'net_profit' => $pnl['net_profit'],
            'purchases' => (int) $report->purchases()['total'],
        ];
    }
}
