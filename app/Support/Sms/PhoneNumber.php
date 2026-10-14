<?php

namespace App\Support\Sms;

/** Turns what people type (0712 345 678, +255712345678, 712345678) into 255712345678, or null when it is not a number. */
class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';
        $cc = (string) config('notifications.sms.country_code', '255');

        if (str_starts_with($digits, $cc)) {
            $local = substr($digits, strlen($cc));
        } elseif (str_starts_with($digits, '0')) {
            $local = substr($digits, 1);
        } else {
            $local = $digits;
        }

        return preg_match('/^[67]\d{8}$/', $local) === 1 ? $cc.$local : null;
    }
}
