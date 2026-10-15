<?php

namespace App\Domain\Integrations\Services;

/**
 * The shop's fiscal identity as returned by TRA at registration, plus the shop's tax choices.
 * `zeroTaxCode` is what a 0% item is reported as: 2 special rate, 3 zero rated, 4 special relief, 5 exempt.
 */
final class FiscalIdentity
{
    public function __construct(
        public readonly string $tin,
        public readonly string $regId,
        public readonly string $efdSerial,
        public readonly string $receiptCode,
        public readonly int $startingGc = 1,
        public readonly float $standardRate = 18.0,
        public readonly int $zeroTaxCode = 3,
    ) {}
}
