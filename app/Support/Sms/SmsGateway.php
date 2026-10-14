<?php

namespace App\Support\Sms;

interface SmsGateway
{
    /**
     * @param  string  $to  International number without "+", for example 255712345678
     *
     * @throws SmsFailedException when the failure is temporary and the send should be retried
     */
    public function send(string $to, string $text, ?string $reference = null): SmsResult;
}
