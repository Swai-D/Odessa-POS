<?php

namespace App\Support\Sms;

use Illuminate\Support\Facades\Log;

/** Default driver: nothing leaves the server, the message is only written to the log. */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $text, ?string $reference = null): SmsResult
    {
        Log::info('SMS (log driver)', ['to' => $to, 'text' => $text]);

        return new SmsResult(true, $reference);
    }
}
