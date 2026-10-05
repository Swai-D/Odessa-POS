<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-tenant gap-free counters for human readable document numbers.
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key', 30);
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 30);
            // Client generated; guarantees a double click or retry never creates a second sale.
            $table->string('idempotency_key', 64);
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('currency', 3)->default('TZS');
            // All money columns are integer minor units.
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('balance_due')->default(0);
            $table->unsignedBigInteger('change_given')->default(0);
            $table->string('payment_status', 20)->default('paid');
            $table->string('note', 500)->nullable();
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'sold_at']);
            $table->index(['tenant_id', 'customer_id', 'balance_due']);
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // Snapshots so receipts stay correct after a product is renamed or repriced.
            $table->string('product_name');
            $table->string('sku', 100);
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('cost_price')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true);
            $table->unsignedBigInteger('gross')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 30);
            $table->unsignedBigInteger('amount');
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'paid_at']);
        });

        Schema::create('held_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('document_sequences');
    }
};
