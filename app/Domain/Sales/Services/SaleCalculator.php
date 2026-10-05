<?php

namespace App\Domain\Sales\Services;

/**
 * Pure, deterministic sale arithmetic on integer minor units.
 *
 * The browser POS (public/js/pos-calc.js) implements the exact same algorithm so the cart total
 * a cashier sees equals what the server charges. Both are checked against the shared fixtures in
 * tests/fixtures/sale-calc-cases.json. Keep them in sync.
 *
 * Steps: gross = qty x price -> order discount allocated across lines -> tax on the discounted
 * amount (inclusive prices extract the tax, exclusive prices add it).
 */
class SaleCalculator
{
    public const DISCOUNT_NONE = 'none';

    public const DISCOUNT_PERCENT = 'percent';

    public const DISCOUNT_FIXED = 'fixed';

    /**
     * @param  list<array{quantity: float|int|string, unit_price: int, tax_rate: float|int|string, tax_inclusive: bool}>  $lines
     * @param  array{type?: string, value?: float|int|string}|null  $discount  percent: 0-100, fixed: minor units
     * @return array{lines: list<array{gross: int, discount: int, tax: int, total: int}>, subtotal: int, discount_total: int, tax_total: int, total: int}
     */
    public function calculate(array $lines, ?array $discount = null): array
    {
        $gross = [];
        foreach ($lines as $line) {
            $quantityMilli = (int) round((float) $line['quantity'] * 1000);
            $gross[] = (int) floor($quantityMilli * $line['unit_price'] / 1000 + 0.5);
        }

        $subtotal = array_sum($gross);
        $discountTotal = $this->discountAmount($subtotal, $discount);
        $allocated = $this->allocate($gross, $discountTotal, $subtotal);

        $result = [];
        $taxTotal = 0;
        $total = 0;

        foreach ($lines as $i => $line) {
            $net = $gross[$i] - $allocated[$i];
            $basisPoints = (int) round((float) $line['tax_rate'] * 100);

            if ($line['tax_inclusive']) {
                $tax = $net - (int) floor($net * 10000 / (10000 + $basisPoints) + 0.5);
                $lineTotal = $net;
            } else {
                $tax = (int) floor($net * $basisPoints / 10000 + 0.5);
                $lineTotal = $net + $tax;
            }

            $taxTotal += $tax;
            $total += $lineTotal;
            $result[] = ['gross' => $gross[$i], 'discount' => $allocated[$i], 'tax' => $tax, 'total' => $lineTotal];
        }

        return [
            'lines' => $result,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }

    /** @param  array{type?: string, value?: float|int|string}|null  $discount */
    private function discountAmount(int $subtotal, ?array $discount): int
    {
        $type = $discount['type'] ?? self::DISCOUNT_NONE;
        $value = (float) ($discount['value'] ?? 0);

        $amount = match ($type) {
            self::DISCOUNT_PERCENT => (int) floor($subtotal * min(max($value, 0), 100) / 100 + 0.5),
            self::DISCOUNT_FIXED => (int) max($value, 0),
            default => 0,
        };

        return min($amount, $subtotal);
    }

    /**
     * Split the discount over the lines in proportion to their gross amount. Whole units left over
     * by rounding go to the first lines that still have room, so the parts always add up exactly.
     *
     * @param  list<int>  $gross
     * @return list<int>
     */
    private function allocate(array $gross, int $discountTotal, int $subtotal): array
    {
        $parts = array_fill(0, count($gross), 0);

        if ($discountTotal <= 0 || $subtotal <= 0) {
            return $parts;
        }

        foreach ($gross as $i => $amount) {
            $parts[$i] = (int) floor($discountTotal * $amount / $subtotal);
        }

        $left = $discountTotal - array_sum($parts);

        foreach ($gross as $i => $amount) {
            if ($left <= 0) {
                break;
            }
            if ($parts[$i] < $amount) {
                $parts[$i]++;
                $left--;
            }
        }

        return $parts;
    }
}
