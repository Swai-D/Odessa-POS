<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // The cashier whose till was closed.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('idempotency_key', 64);
            $table->string('currency', 3)->default('TZS');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            // Integer minor units.
            $table->bigInteger('opening_float')->default(0);
            $table->bigInteger('cash_sales')->default(0);
            $table->bigInteger('cash_refunds')->default(0);
            $table->bigInteger('expected_cash')->default(0);
            $table->bigInteger('counted_cash')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->bigInteger('float_kept')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->bigInteger('sales_total')->default(0);
            // Everything received in the period by method, as a snapshot: {"cash": 120000, "mobile_money": 50000}.
            $table->json('by_method')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'user_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_closings');
    }
};
