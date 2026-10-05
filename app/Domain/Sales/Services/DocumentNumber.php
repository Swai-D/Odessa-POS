<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumber
{
    /**
     * Next number for the current tenant, e.g. "SL-000042". Must run inside the caller's
     * transaction so a rolled back sale does not burn a number.
     */
    public function next(string $key, string $prefix): string
    {
        return DB::transaction(function () use ($key, $prefix): string {
            // createOrFirst is safe when two first-ever sales race to create the counter row.
            DocumentSequence::query()->createOrFirst(['key' => $key], ['last_value' => 0]);

            $sequence = DocumentSequence::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_value');

            return $prefix.str_pad((string) $sequence->last_value, 6, '0', STR_PAD_LEFT);
        });
    }
}
