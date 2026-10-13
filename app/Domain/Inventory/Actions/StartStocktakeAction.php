<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Sales\Services\DocumentNumber;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens a stock count sheet for one warehouse: every active, stock-tracked product (optionally only one category) with
 * the quantity the system expects right now. The client's idempotency key makes a double submit harmless, and a
 * warehouse can only have one open count at a time so two people do not count the same shelves twice.
 */
class StartStocktakeAction
{
    public function __construct(private readonly DocumentNumber $numbers) {}

    /**
     * @param  array{idempotency_key: string, warehouse_id: int|string, category_id?: int|string|null, note?: string|null}  $data
     * @return array{stocktake: Stocktake, created: bool}
     */
    public function handle(array $data, ?User $user): array
    {
        if ($existing = $this->findExisting($data['idempotency_key'])) {
            return ['stocktake' => $existing, 'created' => false];
        }

        try {
            $stocktake = DB::transaction(fn (): Stocktake => $this->create($data, $user));
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $this->findExisting($data['idempotency_key'])) {
                return ['stocktake' => $existing, 'created' => false];
            }

            throw $e;
        }

        return ['stocktake' => $stocktake, 'created' => true];
    }

    private function findExisting(string $key): ?Stocktake
    {
        return Stocktake::query()->where('idempotency_key', $key)->first();
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data, ?User $user): Stocktake
    {
        $warehouse = Warehouse::query()->where('is_active', true)->lockForUpdate()->find($data['warehouse_id']);

        if ($warehouse === null) {
            throw ValidationException::withMessages(['warehouse_id' => __('stocktake.errors.warehouse_unavailable')]);
        }

        if (Stocktake::query()->where('warehouse_id', $warehouse->getKey())->where('status', Stocktake::OPEN)->exists()) {
            throw ValidationException::withMessages(['warehouse_id' => __('stocktake.errors.already_open')]);
        }

        $products = Product::query()
            ->where('is_active', true)
            ->where('track_stock', true)
            ->when(! empty($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        if ($products->isEmpty()) {
            throw ValidationException::withMessages(['category_id' => __('stocktake.errors.no_products')]);
        }

        $quantities = ProductStock::query()->where('warehouse_id', $warehouse->getKey())->pluck('quantity', 'product_id');

        $stocktake = Stocktake::create([
            'number' => $this->numbers->next('stocktake', 'ST-'),
            'idempotency_key' => $data['idempotency_key'],
            'warehouse_id' => $warehouse->getKey(),
            'user_id' => $user?->getKey(),
            'status' => Stocktake::OPEN,
            'note' => $data['note'] ?? null,
            'started_at' => now(),
        ]);

        foreach ($products as $product) {
            $stocktake->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'sku' => $product->sku,
                'expected' => (float) ($quantities[$product->getKey()] ?? 0),
            ]);
        }

        return $stocktake;
    }
}
