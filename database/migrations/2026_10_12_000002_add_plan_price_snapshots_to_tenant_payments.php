<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table): void {
            $table->string('plan_name', 100)->nullable()->after('plan');
            $table->unsignedBigInteger('plan_price_amount')->nullable()->after('plan');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('plan_price_amount');
            $table->string('discount_reason', 250)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_payments', function (Blueprint $table): void {
            $table->dropColumn(['plan_name', 'plan_price_amount', 'discount_amount', 'discount_reason']);
        });
    }
};
