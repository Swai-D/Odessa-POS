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
 * (StockMovement), updates the running balance in ProductStock and keeps the cost batches in step, all inside
 * one transaction.
 */
class StockService
{
    public function __construct(private readonly StockBatchService $batches) {}

    /**
     * @param  float|int|string  $delta  signed quantity: positive adds stock, negative removes it
     * @param  int|null  $unitCost  cost per unit (minor units) of incoming stock; becomes the cost of its batch
     * @param  StockMovement|null  $origin  the sale movement a return undoes, or the transfer-out a transfer-in completes
     * @param  bool  $allowNegative  only for a sale the till already made offline: the goods left the shop, so the ledger must show it
     *
     * @throws InsufficientStockException when the result would be below zero (unless $allowNegative)
     */
    public function move(
        Product $product,
        Warehouse $warehouse,
        float|int|string $delta,
        string $type,
        ?string $reason = null,
        ?User $user = null,
        ?Model $reference = null,
        ?int $unitCost = null,
        ?StockMovement $origin = null,
        bool $allowNegative = false,
    ): StockMovement {
        $delta = round((float) $delta, 3);

        return DB::transaction(function () use ($product, $warehouse, $delta, $type, $reason, $user, $reference, $unitCost, $origin, $allowNegative): StockMovement {
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

            if ($balance < 0 && ! ($allowNegative && $delta < 0)) {
                throw new InsufficientStockException(
                    __('inventory.insufficient_stock', ['product' => $product->name]),
                );
            }

            $stock->update(['quantity' => $balance]);

            $movement = StockMovement::create([
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

            $this->batches->apply($movement, $product, $warehouse, $unitCost, $origin);

            return $movement;
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
