<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 30);
            $table->string('idempotency_key', 64);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('currency', 3)->default('TZS');
            // Integer minor units.
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->unsignedBigInteger('balance_due')->default(0);
            $table->string('payment_status', 20)->default('paid');
            $table->string('note', 500)->nullable();
            $table->timestamp('purchased_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'purchased_at']);
            $table->index(['tenant_id', 'supplier_id', 'balance_due']);
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 100);
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('total');
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_id']);
        });

        Schema::create('purchase_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 30);
            $table->unsignedBigInteger('amount');
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};
