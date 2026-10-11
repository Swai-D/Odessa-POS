<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cost layers. Every receipt of stock becomes a batch that keeps its own unit cost, so buying the same product
 * again at a different price never changes the cost of the units still on the shelf. Sales and write-offs take
 * stock from the oldest batch first (FIFO) and remember which batches they used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            // Integer minor units, per single unit of the product.
            $table->unsignedBigInteger('unit_cost');
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);
            $table->timestamp('received_at')->useCurrent();
            // purchase, opening, adjustment_in, sale_return, transfer_in or legacy (created by this migration).
            $table->string('source', 30);
            $table->nullableMorphs('reference');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id', 'received_at'], 'stock_batches_fifo_index');
        });

        // Which batches a stock movement took its units from, and at what cost. A null batch means the batches
        // could not cover the quantity, so the shortfall was costed at the product's reference cost.
        Schema::create('stock_batch_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_cost');
            // How much of this allocation has come back through a sale return.
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'stock_movement_id']);
        });

        $this->backfillOpeningBatches();
    }

    /** Existing stock becomes one batch per product and warehouse, costed at the product's current cost price. */
    private function backfillOpeningBatches(): void
    {
        $now = now();

        DB::table('product_stocks')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->where('products.track_stock', true)
            ->where('product_stocks.quantity', '>', 0)
            ->orderBy('product_stocks.id')
            ->select([
                'product_stocks.tenant_id', 'product_stocks.product_id', 'product_stocks.warehouse_id',
                'product_stocks.quantity', 'products.cost_price',
            ])
            ->chunk(500, function ($rows) use ($now): void {
                DB::table('stock_batches')->insert($rows->map(fn ($row): array => [
                    'tenant_id' => $row->tenant_id,
                    'product_id' => $row->product_id,
                    'warehouse_id' => $row->warehouse_id,
                    'unit_cost' => $row->cost_price,
                    'quantity_received' => $row->quantity,
                    'quantity_remaining' => $row->quantity,
                    'received_at' => $now,
                    'source' => 'legacy',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batch_allocations');
        Schema::dropIfExists('stock_batches');
    }
};
