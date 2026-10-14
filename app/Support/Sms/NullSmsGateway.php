<?php

namespace App\Support\Sms;

class NullSmsGateway implements SmsGateway
{
    public function send(string $to, string $text, ?string $reference = null): SmsResult
    {
        return new SmsResult(true, $reference);
    }
}
