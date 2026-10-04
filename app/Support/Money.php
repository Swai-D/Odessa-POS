<?php

namespace App\Support;

class Money
{
    /** Convert a user-entered amount (e.g. "1500.50") to integer minor units. */
    public static function toMinor(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Convert integer minor units to a plain decimal string for form inputs. */
    public static function toMajor(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }

    public static function format(int $minorUnits, string $currency = 'TZS'): string
    {
        $amount = abs($minorUnits);
        $formatted = number_format(intdiv($amount, 100), 0, '.', ',')
            .'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);

        return ($minorUnits < 0 ? '-' : '').$currency.' '.$formatted;
    }
}
