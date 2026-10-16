<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockBatchAllocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use DateTimeInterface;

/**
 * Keeps the cost layers (StockBatch) in step with the stock ledger. It is only called from StockService::move()
 * inside the same transaction, so batches and ProductStock can never disagree.
 *
 * - Stock coming in creates a batch carrying its own unit cost.
 * - Stock going out is taken from the oldest batch first (FIFO) and recorded as allocations on the movement.
 * - A sale return puts the units back into the batches the sale took them from.
 * - A transfer carries the batches (cost and age) from the source warehouse to the destination.
 */
class StockBatchService
{
    /**
     * @param  int|null  $unitCost  cost of the incoming units in minor units; falls back to the latest known cost
     * @param  StockMovement|null  $origin  the sale movement a return undoes, or the transfer-out a transfer-in completes
     */
    public function apply(StockMovement $movement, Product $product, Warehouse $warehouse, ?int $unitCost, ?StockMovement $origin): void
    {
        $quantity = round((float) $movement->quantity, 3);

        if ($quantity < 0) {
            $this->consume($movement, $product, $warehouse, abs($quantity));

            return;
        }

        $this->applyIncoming($movement, $product, $warehouse, $unitCost, $origin, $quantity);

        // Only stock that arrives into a deficit (left by an offline sale) needs the batches trimmed.
        if (round((float) $movement->balance_after - $quantity, 3) < 0) {
            $this->settleDeficit($movement, $product, $warehouse);
        }
    }

    private function applyIncoming(StockMovement $movement, Product $product, Warehouse $warehouse, ?int $unitCost, ?StockMovement $origin, float $quantity): void
    {
        if ($quantity === 0.0) {
            return;
        }

        if ($movement->type === StockMovement::SALE_RETURN && $origin) {
            $this->restore($movement, $product, $warehouse, $quantity, $origin, $unitCost);

            return;
        }

        if ($movement->type === StockMovement::TRANSFER_IN && $origin) {
            $this->carry($movement, $product, $warehouse, $origin);

            return;
        }

        $this->receive($product, $warehouse, $quantity, $unitCost ?? $this->defaultCost($product, $warehouse), $movement->type, $movement);
    }

    /**
     * An offline sale can leave a warehouse below zero. Units that arrive afterwards first cover that gap, so the
     * batches never hold more than the stock really is: shrink the newest batches by the excess.
     */
    private function settleDeficit(StockMovement $movement, Product $product, Warehouse $warehouse): void
    {
        $batches = StockBatch::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $excess = round((float) $batches->sum('quantity_remaining') - max((float) $movement->balance_after, 0.0), 3);

        foreach ($batches as $batch) {
            if ($excess <= 0) {
                break;
            }

            $cut = round(min((float) $batch->quantity_remaining, $excess), 3);
            $batch->update(['quantity_remaining' => round((float) $batch->quantity_remaining - $cut, 3)]);
            $excess = round($excess - $cut, 3);
        }
    }

    /** Cost to use when the caller gives none: the most recent batch in the warehouse, else the product's reference cost. */
    public function defaultCost(Product $product, Warehouse $warehouse): int
    {
        $latest = StockBatch::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        return $latest ? (int) $latest->unit_cost : (int) $product->cost_price;
    }

    private function receive(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        int $unitCost,
        string $source,
        ?StockMovement $movement = null,
        ?DateTimeInterface $receivedAt = null,
    ): StockBatch {
        return StockBatch::create([
            'product_id' => $product->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'unit_cost' => $unitCost,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'received_at' => $receivedAt ?? now(),
            'source' => $source,
            'reference_type' => $movement?->reference_type,
            'reference_id' => $movement?->reference_id,
        ]);
    }

    /** Take $quantity from the oldest batches first. Whatever the batches cannot cover is costed at the product's cost. */
    private function consume(StockMovement $movement, Product $product, Warehouse $warehouse, float $quantity): void
    {
        $remaining = $quantity;

        $batches = StockBatch::query()
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouse->getKey())
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = round(min((float) $batch->quantity_remaining, $remaining), 3);

            if ($take <= 0) {
                continue;
            }

            $batch->update(['quantity_remaining' => round((float) $batch->quantity_remaining - $take, 3)]);

            StockBatchAllocation::create([
                'stock_movement_id' => $movement->getKey(),
                'stock_batch_id' => $batch->getKey(),
                'quantity' => $take,
                'unit_cost' => $batch->unit_cost,
            ]);

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0) {
            StockBatchAllocation::create([
                'stock_movement_id' => $movement->getKey(),
                'stock_batch_id' => null,
                'quantity' => $remaining,
                'unit_cost' => (int) $product->cost_price,
            ]);
        }
    }

    /** Put returned units back where the sale took them from, newest allocation first. */
    private function restore(
        StockMovement $movement,
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        StockMovement $sale,
        ?int $unitCost,
    ): void {
        $remaining = $quantity;

        $allocations = StockBatchAllocation::query()
            ->where('stock_movement_id', $sale->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $take = round(min((float) $allocation->quantity - (float) $allocation->returned_quantity, $remaining), 3);

            if ($take <= 0) {
                continue;
            }

            $allocation->update(['returned_quantity' => round((float) $allocation->returned_quantity + $take, 3)]);

            $batch = $allocation->stock_batch_id
                ? StockBatch::query()->lockForUpdate()->find($allocation->stock_batch_id)
                : null;

            if ($batch) {
                $batch->update(['quantity_remaining' => round((float) $batch->quantity_remaining + $take, 3)]);
            } else {
                $this->receive($product, $warehouse, $take, (int) $allocation->unit_cost, StockBatch::SOURCE_RETURN, $movement);
            }

            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0) {
            $this->receive(
                $product,
                $warehouse,
                $remaining,
                $unitCost ?? $this->defaultCost($product, $warehouse),
                StockBatch::SOURCE_RETURN,
                $movement,
            );
        }
    }

    /** Recreate the batches the source warehouse gave up, with the same unit cost and age, in the destination. */
    private function carry(StockMovement $movement, Product $product, Warehouse $destination, StockMovement $out): void
    {
        $allocations = StockBatchAllocation::query()
            ->with('batch')
            ->where('stock_movement_id', $out->getKey())
            ->orderBy('id')
            ->get();

        foreach ($allocations as $allocation) {
            $this->receive(
                $product,
                $destination,
                (float) $allocation->quantity,
                (int) $allocation->unit_cost,
                StockBatch::SOURCE_TRANSFER,
                $movement,
                $allocation->batch?->received_at,
            );
        }
    }
}
