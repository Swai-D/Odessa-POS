<?php

namespace App\Domain\Sales\Services;

use RuntimeException;

/** AzamPay refused the request or could not be reached. The message is safe to log; it never holds credentials. */
class AzamPayException extends RuntimeException {}
