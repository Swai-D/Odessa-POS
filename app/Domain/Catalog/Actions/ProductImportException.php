<?php

namespace App\Domain\Catalog\Actions;

use RuntimeException;

/** The CSV was refused as a whole; nothing was saved. Carries the problems found, one line each. */
class ProductImportException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems, public readonly int $total)
    {
        parent::__construct($problems[0] ?? 'Import failed');
    }
}
