<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            // The supplier's lot number and the date the goods go off. Only filled for products that track them.
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
            $table->index(['tenant_id', 'expiry_date']);
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->string('batch_number', 60)->nullable();
            $table->date('expiry_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'expiry_date']);
            $table->dropColumn(['batch_number', 'expiry_date']);
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropColumn(['batch_number', 'expiry_date']);
        });
    }
};
