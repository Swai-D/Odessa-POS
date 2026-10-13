<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('idempotency_key', 64);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('note', 500)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'warehouse_id', 'status']);
        });

        Schema::create('stocktake_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('stocktake_id')->constrained('stocktakes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Snapshots so the sheet stays readable after a product is renamed.
            $table->string('product_name');
            $table->string('sku', 100);
            // What the system said at the start, what was counted on the shelf (null = not counted), and the
            // difference that was posted to stock when the count was completed.
            $table->decimal('expected', 14, 3);
            $table->decimal('counted', 14, 3)->nullable();
            $table->decimal('adjusted', 14, 3)->nullable();
            $table->timestamps();

            $table->unique(['stocktake_id', 'product_id']);
            $table->index(['tenant_id', 'stocktake_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');
    }
};
