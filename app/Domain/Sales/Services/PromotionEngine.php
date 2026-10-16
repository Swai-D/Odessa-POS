<?php

namespace App\Domain\Sales\Services;

/**
 * Works out which promotion gives each basket line its discount. Pure integer arithmetic on minor units.
 * The browser has the same algorithm (public/js/pos-promo.js); both run against tests/fixtures/promotion-cases.json.
 *
 * - Automatic promotions apply on their own while they are running; a promotion with a code applies only when
 *   that code was entered.
 * - Each line gets at most ONE promotion: the one that takes the most off the line (lowest id on a tie).
 * - The discount is on the line before any order discount the cashier adds.
 */
class PromotionEngine
{
    /**
     * @param  list<array<string, mixed>>  $promotions  Promotion::toEngine() rows
     * @param  list<array{product_id: int, category_id: int|null, quantity: float|int|string, unit_price: int}>  $lines
     * @param  array{date: string, weekday: int, minutes: int}  $at  the shop's local date (Y-m-d), ISO weekday and minutes since midnight
     * @return list<array{promotion_id: int, name: string, amount: int}|null> one entry per line, null when no promotion applies
     */
    public function evaluate(array $promotions, array $lines, ?string $coupon, array $at): array
    {
        $coupon = $coupon !== null && trim($coupon) !== '' ? strtoupper(trim($coupon)) : null;

        $usable = array_values(array_filter($promotions, function (array $promotion) use ($coupon, $at): bool {
            if (! $this->running($promotion, $at)) {
                return false;
            }

            $code = $promotion['code'] ?? null;

            return $code === null || $code === '' || ($coupon !== null && strtoupper((string) $code) === $coupon);
        }));

        $result = [];

        foreach ($lines as $line) {
            $best = null;

            foreach ($usable as $promotion) {
                if (! $this->covers($promotion, $line)) {
                    continue;
                }

                $amount = $this->discount($promotion, $line);

                if ($amount > 0 && ($best === null || $amount > $best['amount'] || ($amount === $best['amount'] && $promotion['id'] < $best['promotion_id']))) {
                    $best = ['promotion_id' => (int) $promotion['id'], 'name' => (string) $promotion['name'], 'amount' => $amount];
                }
            }

            $result[] = $best;
        }

        return $result;
    }

    /** @param  array<string, mixed>  $promotion  @param  array{date: string, weekday: int, minutes: int}  $at */
    public function running(array $promotion, array $at): bool
    {
        if (! ($promotion['is_active'] ?? false)) {
            return false;
        }

        if (! empty($promotion['starts_on']) && $at['date'] < $promotion['starts_on']) {
            return false;
        }

        if (! empty($promotion['ends_on']) && $at['date'] > $promotion['ends_on']) {
            return false;
        }

        if (! empty($promotion['days']) && ! in_array($at['weekday'], $promotion['days'], true)) {
            return false;
        }

        $from = $this->minutes($promotion['time_from'] ?? null);
        $to = $this->minutes($promotion['time_to'] ?? null);

        if ($from !== null && $to !== null) {
            // A window that ends before it starts runs past midnight (22:00 to 02:00).
            return $from <= $to
                ? $at['minutes'] >= $from && $at['minutes'] < $to
                : $at['minutes'] >= $from || $at['minutes'] < $to;
        }

        if ($from !== null && $at['minutes'] < $from) {
            return false;
        }

        return ! ($to !== null && $at['minutes'] >= $to);
    }

    private function minutes(?string $time): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /** @param  array<string, mixed>  $promotion  @param  array{product_id: int, category_id: int|null}  $line */
    private function covers(array $promotion, array $line): bool
    {
        return match ($promotion['scope']) {
            'products' => in_array($line['product_id'], $promotion['scope_ids'], true),
            'categories' => $line['category_id'] !== null && in_array($line['category_id'], $promotion['scope_ids'], true),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $promotion
     * @param  array{quantity: float|int|string, unit_price: int}  $line
     */
    private function discount(array $promotion, array $line): int
    {
        $quantityMilli = (int) round((float) $line['quantity'] * 1000);
        $gross = (int) floor($quantityMilli * $line['unit_price'] / 1000 + 0.5);
        $value = (int) $promotion['value'];

        $amount = match ($promotion['type']) {
            'percent' => $this->percentOff($gross, $value),
            'fixed' => $this->perUnitOff($quantityMilli, $line['unit_price'], $value),
            'bulk' => $quantityMilli >= (int) round((float) ($promotion['min_qty'] ?? 0) * 1000)
                ? (($promotion['value_kind'] ?? 'percent') === 'fixed'
                    ? $this->perUnitOff($quantityMilli, $line['unit_price'], $value)
                    : $this->percentOff($gross, $value))
                : 0,
            'bogo' => $this->freeUnits($quantityMilli, (int) ($promotion['buy_qty'] ?? 0), (int) ($promotion['get_qty'] ?? 0)) * $line['unit_price'],
            default => 0,
        };

        return min(max($amount, 0), $gross);
    }

    private function percentOff(int $gross, int $hundredths): int
    {
        return (int) floor($gross * min($hundredths, 10000) / 10000 + 0.5);
    }

    private function perUnitOff(int $quantityMilli, int $unitPrice, int $perUnit): int
    {
        return (int) floor($quantityMilli * min($perUnit, $unitPrice) / 1000 + 0.5);
    }

    /** Whole groups of (buy + get) units give `get` units free in each. */
    private function freeUnits(int $quantityMilli, int $buy, int $get): int
    {
        if ($buy < 1 || $get < 1) {
            return 0;
        }

        return intdiv(intdiv($quantityMilli, 1000), $buy + $get) * $get;
    }
}
