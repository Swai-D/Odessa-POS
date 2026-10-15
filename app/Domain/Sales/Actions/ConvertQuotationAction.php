<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Quotation;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an accepted quotation into a real sale through the normal checkout, so stock, tax, credit limits and
 * payments follow the same rules as at the till. Prices are the products' current prices (the server never trusts
 * old numbers); the page warns when they differ from the quote. The sale is keyed to the quotation, so a double
 * click or retry returns the same sale.
 */
class ConvertQuotationAction
{
    public function __construct(private readonly CheckoutAction $checkout) {}

    /**
     * @param  array{warehouse_id: int|string, payments?: list<array{method: string, amount: int, reference?: string|null}>}  $data
     */
    public function handle(Quotation $quotation, array $data, ?User $user): Sale
    {
        if ($quotation->status === Quotation::CONVERTED && $quotation->sale_id !== null) {
            return Sale::query()->findOrFail($quotation->sale_id);
        }

        if (! $quotation->canConvert()) {
            throw ValidationException::withMessages(['status' => __('quotations.errors.cannot_convert')]);
        }

        $quotation->loadMissing('items');

        $payload = [
            'idempotency_key' => 'quotation-'.$quotation->getKey(),
            'customer_id' => $quotation->customer_id,
            'warehouse_id' => $data['warehouse_id'],
            'items' => $quotation->items->map(fn ($item): array => ['product_id' => $item->product_id, 'quantity' => (float) $item->quantity])->all(),
            'payments' => $data['payments'] ?? [],
            'note' => __('quotations.sale_note', ['number' => $quotation->number]),
        ];

        if ($quotation->items->contains(fn ($item): bool => $item->product_id === null)) {
            throw ValidationException::withMessages(['items' => __('pos.errors.product_unavailable')]);
        }

        if ((float) $quotation->discount_total > 0 && $quotation->subtotal > 0) {
            $payload['discount'] = ['type' => 'fixed', 'value' => $quotation->discount_total];
        }

        return DB::transaction(function () use ($quotation, $payload, $user): Sale {
            ['sale' => $sale] = $this->checkout->handle($payload, $user);
            $quotation->update(['status' => Quotation::CONVERTED, 'sale_id' => $sale->getKey()]);

            return $sale;
        });
    }
}
