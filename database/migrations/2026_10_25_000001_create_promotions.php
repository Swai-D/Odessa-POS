<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            // percent: value = hundredths of a percent (1250 = 12.5%). fixed: value = amount off each unit, minor units.
            // bogo: buy_qty paid, get_qty free. bulk: from min_qty units, value as percent or fixed (value_kind).
            $table->string('type', 10);
            $table->string('value_kind', 10)->nullable();
            $table->unsignedBigInteger('value')->default(0);
            $table->decimal('min_qty', 12, 3)->nullable();
            $table->unsignedInteger('buy_qty')->nullable();
            $table->unsignedInteger('get_qty')->nullable();
            // all | products | categories; scope_ids lists their ids.
            $table->string('scope', 12)->default('all');
            $table->json('scope_ids')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // ISO weekdays 1 (Mon) to 7 (Sun), comma separated; empty = every day.
            $table->string('days', 20)->nullable();
            $table->string('time_from', 5)->nullable();
            $table->string('time_to', 5)->nullable();
            // A coupon is a promotion with a code: it only applies when the cashier enters the code.
            $table->string('code', 40)->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->string('promotion_name', 120)->nullable();
            // Part of `discount` that came from the promotion (the rest is the cashier's order discount).
            $table->unsignedBigInteger('promo_discount')->default(0);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('promo_total')->default(0);
            $table->string('coupon_code', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['promo_total', 'coupon_code']);
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['promotion_name', 'promo_discount']);
        });
        Schema::dropIfExists('promotions');
    }
};
