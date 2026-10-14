<?php

namespace App\Support\Sms;

final class SmsResult
{
    public function __construct(public readonly bool $accepted, public readonly ?string $reference = null, public readonly ?string $error = null) {}
}
