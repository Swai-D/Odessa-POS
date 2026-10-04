<?php

namespace App\Support;

class Money
{
    public static function format(int $minorUnits, string $currency = 'TZS'): string
    {
        $amount = abs($minorUnits);
        $formatted = number_format(intdiv($amount, 100), 0, '.', ',')
            .'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);

        return ($minorUnits < 0 ? '-' : '').$currency.' '.$formatted;
    }
}
