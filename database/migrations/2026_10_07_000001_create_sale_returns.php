<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->unsignedBigInteger('returned_amount')->default(0);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('returned_total')->default(0);
        });

        Schema::create('sale_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 30);
            // Integer minor units. total = credit_applied (reduces the customer's debt) + refunded (paid out).
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('credit_applied')->default(0);
            $table->unsignedBigInteger('refunded')->default(0);
            $table->string('refund_method', 30)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'returned_at']);
        });

        Schema::create('sale_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->index(['tenant_id', 'sale_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('returned_total');
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['returned_quantity', 'returned_amount']);
        });
    }
};
