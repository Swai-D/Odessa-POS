<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\TillClosing;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What a cashier's till should hold right now. The period runs from their last closing (or the start of today
 * for the first one) to now, so consecutive closings never overlap and nothing is left out between them.
 *
 * Expected cash = the float left in the drawer last time + cash received - cash refunded. It covers sales, customer
 * debt payments and returns made by this user only; money paid out of the drawer for anything else (supplier
 * payments, expenses) is not tracked here, so a shortfall by that amount is expected and shows up as a difference.
 */
class TillSummary
{
    /**
     * @return array{start: Carbon, end: Carbon, opening_float: int, cash_sales: int, cash_refunds: int, expected_cash: int, by_method: array<string, int>, sales_count: int, sales_total: int}
     */
    public function for(User $user, ?Carbon $now = null): array
    {
        $end = $now ?? Carbon::now();
        $last = TillClosing::query()->where('user_id', $user->getKey())->orderByDesc('id')->first();

        $start = $last?->period_end ?? $end->copy()->startOfDay();
        $opening = (int) ($last->float_kept ?? 0);

        /** @var array<string, int> $byMethod */
        $byMethod = Payment::query()
            ->where('user_id', $user->getKey())
            ->where('paid_at', '>', $start)->where('paid_at', '<=', $end)
            ->groupBy('method')
            ->selectRaw('method, SUM(amount) as amount')
            ->get()
            ->mapWithKeys(fn (Payment $row): array => [$row->method => (int) $row->getAttribute('amount')])
            ->all();

        $cashSales = $byMethod[Payment::CASH] ?? 0;

        $cashRefunds = (int) SaleReturn::query()
            ->where('user_id', $user->getKey())
            ->where('refund_method', Payment::CASH)
            ->where('returned_at', '>', $start)->where('returned_at', '<=', $end)
            ->sum('refunded');

        $sales = Sale::query()->where('user_id', $user->getKey())->where('sold_at', '>', $start)->where('sold_at', '<=', $end);

        return [
            'start' => $start->copy(),
            'end' => $end->copy(),
            'opening_float' => $opening,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'expected_cash' => $opening + $cashSales - $cashRefunds,
            'by_method' => $byMethod,
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (int) (clone $sales)->sum('total'),
        ];
    }
}
