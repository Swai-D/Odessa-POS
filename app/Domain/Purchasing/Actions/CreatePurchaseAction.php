<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Sales\Services\DocumentNumber;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records goods received from a supplier: a purchase with its lines, the stock coming in through the
 * ledger, and any payment made now. The client's idempotency key makes a double submit harmless.
 */
class CreatePurchaseAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * Money values are integer minor units.
     *
     * @param  array{idempotency_key: string, supplier_id: int|string, warehouse_id: int|string, reference?: string|null, note?: string|null, update_cost?: bool, items: list<array{product_id: int|string, quantity: float|int|string, unit_cost: int, batch_number?: string|null, expiry_date?: string|null}>, payments?: list<array{method: string, amount: int, reference?: string|null}>}  $data
     * @return array{purchase: Purchase, created: bool}
     */
    public function handle(array $data, ?User $user): array
    {
        if ($existing = $this->findExisting($data['idempotency_key'])) {
            return ['purchase' => $existing, 'created' => false];
        }

        try {
            $purchase = DB::transaction(fn (): Purchase => $this->create($data, $user));
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $this->findExisting($data['idempotency_key'])) {
                return ['purchase' => $existing, 'created' => false];
            }

            throw $e;
        }

        return ['purchase' => $purchase, 'created' => true];
    }

    private function findExisting(string $key): ?Purchase
    {
        return Purchase::query()->where('idempotency_key', $key)->first();
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data, ?User $user): Purchase
    {
        $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($data['warehouse_id']);

        $products = Product::query()->with('unit')->where('is_active', true)
            ->whereIn('id', array_unique(array_map(fn (array $row): int => (int) $row['product_id'], $data['items'])))
            ->orderBy('id')->get()->keyBy('id');

        if ($products->count() !== count(array_unique(array_map(fn (array $row): int => (int) $row['product_id'], $data['items'])))) {
            throw ValidationException::withMessages(['items' => __('pos.errors.product_unavailable')]);
        }

        // Merge repeated products so each gets one line and one stock movement. Stock with a different batch
        // number or expiry date stays a line of its own, because it becomes its own batch on the shelf.
        $lines = [];
        foreach ($data['items'] as $row) {
            $id = (int) $row['product_id'];
            $product = $products[$id];
            $quantity = round((float) $row['quantity'], 3);
            $number = $product->track_batch ? (trim((string) ($row['batch_number'] ?? '')) ?: null) : null;
            $expiry = $product->track_expiry ? ($row['expiry_date'] ?? null) : null;
            $key = $id.'|'.$number.'|'.$expiry;

            if (isset($lines[$key])) {
                if ($lines[$key]['unit_cost'] !== (int) $row['unit_cost']) {
                    throw ValidationException::withMessages(['items' => __('purchases.errors.cost_mismatch')]);
                }
                $lines[$key]['quantity'] = round($lines[$key]['quantity'] + $quantity, 3);
            } else {
                $lines[$key] = ['product_id' => $id, 'quantity' => $quantity, 'unit_cost' => (int) $row['unit_cost'], 'batch_number' => $number, 'expiry_date' => $expiry];
            }
        }

        $total = 0;
        foreach ($lines as $key => $line) {
            $product = $products[$line['product_id']];

            if (! ($product->is_weighed || $product->unit?->allow_decimal) && fmod($line['quantity'], 1.0) !== 0.0) {
                throw ValidationException::withMessages(['items' => __('purchases.errors.whole_quantity', ['product' => $product->name])]);
            }

            $lines[$key]['total'] = (int) round($line['quantity'] * $line['unit_cost']);
            $total += $lines[$key]['total'];
        }

        $payments = $data['payments'] ?? [];
        $paid = array_sum(array_column($payments, 'amount'));

        if ($paid > $total) {
            throw ValidationException::withMessages(['payments' => __('purchases.errors.overpaid')]);
        }

        $balance = $total - $paid;

        $purchase = Purchase::create([
            'number' => $this->numbers->next('purchase', 'PU-'),
            'idempotency_key' => $data['idempotency_key'],
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $warehouse->getKey(),
            'user_id' => $user?->getKey(),
            'reference' => $data['reference'] ?? null,
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'total' => $total,
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'payment_status' => match (true) {
                $balance === 0 => Purchase::PAID,
                $paid > 0 => Purchase::PARTIAL,
                default => Purchase::UNPAID,
            },
            'note' => $data['note'] ?? null,
            'purchased_at' => now(),
        ]);

        foreach ($lines as $line) {
            $product = $products[$line['product_id']];

            $purchase->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total' => $line['total'],
                'batch_number' => $line['batch_number'],
                'expiry_date' => $line['expiry_date'],
            ]);

            if ($product->track_stock) {
                $this->stock->move(
                    $product, $warehouse, $line['quantity'], StockMovement::PURCHASE, $purchase->number, $user, $purchase,
                    unitCost: $line['unit_cost'], lot: ['number' => $line['batch_number'], 'expiry' => $line['expiry_date']],
                );
            }

            // Only the reference cost shown on the product; stock already on the shelf keeps the cost it was bought at.
            if (! empty($data['update_cost'])) {
                $product->update(['cost_price' => $line['unit_cost']]);
            }
        }

        foreach ($payments as $payment) {
            $purchase->payments()->create([
                'user_id' => $user?->getKey(),
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'reference' => $payment['reference'] ?? null,
                'paid_at' => now(),
            ]);
        }

        return $purchase->load(['items', 'payments', 'supplier']);
    }
}
