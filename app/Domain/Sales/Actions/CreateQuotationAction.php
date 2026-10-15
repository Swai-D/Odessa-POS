<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Sales\Models\Quotation;
use App\Domain\Sales\Services\DocumentNumber;
use App\Domain\Sales\Services\SaleCalculator;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a quotation. Like a sale, the server decides prices, tax and totals from the product list; the browser only
 * sends product ids and quantities. The same idempotency key returns the first quotation instead of making a second.
 */
class CreateQuotationAction
{
    public function __construct(private readonly SaleCalculator $calculator, private readonly DocumentNumber $numbers) {}

    /** @param  array{idempotency_key: string, customer_id?: int|string|null, customer_name?: string|null, valid_until?: string|null, discount_percent?: float|int|string|null, note?: string|null, items: list<array{product_id: int|string, quantity: float|int|string}>}  $data */
    public function handle(array $data, ?User $user): Quotation
    {
        $existing = Quotation::query()->where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): Quotation => $this->create($data, $user));
        } catch (UniqueConstraintViolationException $e) {
            return Quotation::query()->where('idempotency_key', $data['idempotency_key'])->first() ?? throw $e;
        }
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data, ?User $user): Quotation
    {
        $quantities = [];
        foreach ($data['items'] as $item) {
            $id = (int) $item['product_id'];
            $quantities[$id] = round(($quantities[$id] ?? 0) + (float) $item['quantity'], 3);
        }

        $products = Product::query()->where('is_active', true)->whereIn('id', array_keys($quantities))->orderBy('id')->get();

        if ($products->count() !== count($quantities)) {
            throw ValidationException::withMessages(['items' => __('pos.errors.product_unavailable')]);
        }

        $lines = [];
        foreach ($products as $product) {
            $lines[] = [
                'product' => $product,
                'quantity' => $quantities[$product->getKey()],
                'unit_price' => $product->selling_price,
                'tax_rate' => (float) $product->tax_rate,
                'tax_inclusive' => $product->tax_inclusive,
            ];
        }

        $percent = (float) ($data['discount_percent'] ?? 0);
        $totals = $this->calculator->calculate($lines, $percent > 0 ? ['type' => SaleCalculator::DISCOUNT_PERCENT, 'value' => $percent] : null);

        if ($totals['total'] <= 0) {
            throw ValidationException::withMessages(['items' => __('pos.errors.nothing_to_charge')]);
        }

        $quotation = Quotation::create([
            'number' => $this->numbers->next('quotation', 'QT-'),
            'idempotency_key' => $data['idempotency_key'],
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => ($data['customer_id'] ?? null) ? null : ($data['customer_name'] ?? null),
            'user_id' => $user?->getKey(),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'status' => Quotation::DRAFT,
            'valid_until' => $data['valid_until'] ?? null,
            'note' => $data['note'] ?? null,
            'quoted_at' => now(),
        ]);

        foreach ($lines as $i => $line) {
            /** @var Product $product */
            $product = $line['product'];

            $quotation->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_rate' => $line['tax_rate'],
                'tax_inclusive' => $line['tax_inclusive'],
                'gross' => $totals['lines'][$i]['gross'],
                'discount' => $totals['lines'][$i]['discount'],
                'tax' => $totals['lines'][$i]['tax'],
                'total' => $totals['lines'][$i]['total'],
            ]);
        }

        return $quotation->load('items');
    }
}
