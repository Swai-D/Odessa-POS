<?php

namespace App\Support\Help;

use App\Models\SubscriptionPlan;
use App\Support\Money;

/**
 * The plan comparison table for the Help centre, built from the plans the platform admin has set up,
 * so the article can never disagree with the real prices, features and limits.
 */
class PlanTable
{
    public const PLACEHOLDER = '{{plans-table}}';

    public function markdown(): string
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            return '';
        }

        $yes = (string) __('app.yes');
        $no = (string) __('app.no');
        $currency = (string) config('pos.default_currency');
        $row = static fn (array $cells): string => '| '.implode(' | ', $cells).' |';

        $lines = [
            $row(['', ...$plans->map(fn (SubscriptionPlan $p): string => $p->name)->all()]),
            $row(array_fill(0, $plans->count() + 1, '---')),
            $row([(string) __('help.plan_table.core'), ...array_fill(0, $plans->count(), $yes)]),
        ];

        foreach ((array) config('plans.features') as $feature) {
            $lines[] = $row([
                (string) __('plans.features.'.$feature),
                ...$plans->map(fn (SubscriptionPlan $p): string => in_array('*', $p->features, true) || in_array($feature, $p->features, true) ? $yes : $no)->all(),
            ]);
        }

        foreach (['users', 'warehouses'] as $limit) {
            $lines[] = $row([
                (string) __('help.plan_table.'.$limit),
                ...$plans->map(fn (SubscriptionPlan $p): string => isset($p->limits[$limit]) ? (string) $p->limits[$limit] : (string) __('help.plan_table.unlimited'))->all(),
            ]);
        }

        $price = static fn (?int $amount): string => $amount === null ? '-' : Money::format($amount, $currency);
        $lines[] = $row([(string) __('help.plan_table.monthly'), ...$plans->map(fn (SubscriptionPlan $p): string => $price($p->monthly_price))->all()]);
        $lines[] = $row([(string) __('help.plan_table.annual'), ...$plans->map(fn (SubscriptionPlan $p): string => $price($p->annual_price))->all()]);

        return implode("\n", $lines);
    }
}
