<?php

namespace App\Domain\Integrations\Services;

use RuntimeException;

/** A sale or a setting that cannot be turned into a valid TRA receipt. The message says what to fix. */
class FiscalException extends RuntimeException {}
