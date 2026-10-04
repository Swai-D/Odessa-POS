<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for changing stock. Every change writes an immutable ledger row
 * (StockMovement) and updates the running balance in ProductStock inside one transaction.
 */
class StockService
{
    /**
     * @param  float|int|string  $delta  signed quantity: positive adds stock, negative removes it
     *
     * @throws InsufficientStockException when the result would be below zero
     */
    public function move(
        Product $product,
        Warehouse $warehouse,
        float|int|string $delta,
        string $type,
        ?string $reason = null,
        ?User $user = null,
        ?Model $reference = null,
    ): StockMovement {
        $delta = round((float) $delta, 3);

        return DB::transaction(function () use ($product, $warehouse, $delta, $type, $reason, $user, $reference): StockMovement {
            $stock = ProductStock::query()
                ->where('product_id', $product->getKey())
                ->where('warehouse_id', $warehouse->getKey())
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = ProductStock::create([
                    'product_id' => $product->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'quantity' => 0,
                ]);
            }

            $balance = round((float) $stock->quantity + $delta, 3);

            if ($balance < 0) {
                throw new InsufficientStockException(
                    __('inventory.insufficient_stock', ['product' => $product->name]),
                );
            }

            $stock->update(['quantity' => $balance]);

            return StockMovement::create([
                'product_id' => $product->getKey(),
                'warehouse_id' => $warehouse->getKey(),
                'user_id' => $user?->getKey(),
                'type' => $type,
                'quantity' => $delta,
                'balance_after' => $balance,
                'reason' => $reason,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }

    public function quantity(Product $product, ?Warehouse $warehouse = null): float
    {
        $query = ProductStock::query()->where('product_id', $product->getKey());

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->getKey());
        }

        return (float) $query->sum('quantity');
    }
}
