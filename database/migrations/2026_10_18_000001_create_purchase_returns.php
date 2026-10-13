<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->decimal('returned_quantity', 14, 3)->default(0);
            $table->unsignedBigInteger('returned_amount')->default(0);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->unsignedBigInteger('returned_total')->default(0);
        });

        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 30);
            // Integer minor units. total = credit_applied (reduces what we owe the supplier) + refunded (money the supplier gave back).
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('credit_applied')->default(0);
            $table->unsignedBigInteger('refunded')->default(0);
            $table->string('refund_method', 30)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_id']);
            $table->index(['tenant_id', 'returned_at']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('returned_total');
        });
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropColumn(['returned_quantity', 'returned_amount']);
        });
    }
};
