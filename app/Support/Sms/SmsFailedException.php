<?php

namespace App\Support\Sms;

use RuntimeException;

/** A temporary failure (network, provider error). A queued job that throws this is tried again. */
class SmsFailedException extends RuntimeException {}
