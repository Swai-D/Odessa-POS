<?php

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\FiscalReceipt;
use App\Domain\Integrations\Services\FiscalIdentity;
use App\Domain\Integrations\Services\FiscalXmlBuilder;
use App\Domain\Sales\Models\DocumentSequence;
use App\Domain\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Gives a sale its fiscal numbers and freezes the receipt XML. Must run inside the shop's tenant context.
 *
 * TRA rules enforced here: the global counter (GC, also the receipt number) goes up by exactly one per receipt with
 * no gaps and is never reused; the daily counter (DC) restarts at 1 each day; the numbers and the XML never change
 * after issue. A sale that already has a fiscal receipt returns it unchanged. The same transaction that takes the
 * numbers saves the receipt, so a failure cannot burn a number and leave a gap.
 */
class IssueFiscalReceiptAction
{
    /** TRA works in Tanzanian time, whatever the server clock says. */
    private const TIMEZONE = 'Africa/Dar_es_Salaam';

    public function __construct(private readonly FiscalXmlBuilder $xml) {}

    public function handle(Sale $sale, FiscalIdentity $identity): FiscalReceipt
    {
        return DB::transaction(function () use ($sale, $identity): FiscalReceipt {
            $existing = FiscalReceipt::query()->where('sale_id', $sale->getKey())->first();

            if ($existing !== null) {
                return $existing;
            }

            $at = now(self::TIMEZONE);
            $gc = $this->next('fiscal_gc', $identity->startingGc);
            $dc = $this->next('fiscal_dc_'.$at->format('Ymd'), 1);

            return FiscalReceipt::query()->create([
                'sale_id' => $sale->getKey(),
                'gc' => $gc,
                'dc' => $dc,
                'znum' => (int) $at->format('Ymd'),
                'rctvnum' => $identity->receiptCode.$gc,
                'issued_at' => $at->copy()->utc(),
                'payload' => $this->xml->receipt($sale, $identity, $gc, $dc, $at),
                'status' => FiscalReceipt::PENDING,
            ]);
        });
    }

    /** Next value of a gap-free counter; `$first` is its first value. The row lock makes concurrent sales wait their turn. */
    private function next(string $key, int $first): int
    {
        DocumentSequence::query()->createOrFirst(['key' => $key], ['last_value' => $first - 1]);

        $sequence = DocumentSequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_value');

        return (int) $sequence->last_value;
    }
}
