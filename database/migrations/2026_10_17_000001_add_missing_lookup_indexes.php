<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Indexes for lookups the app already does but that had none (PostgreSQL does not index foreign keys by itself):
 | a shop's users, the branch report (sales per warehouse over time), the product list filters by category and brand,
 | stock per warehouse, and the stock movement list. Adding an index changes no data and no behaviour, only speed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index('tenant_id', 'users_tenant_id_index');
        });
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['tenant_id', 'warehouse_id', 'sold_at'], 'sales_tenant_warehouse_sold_index');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['tenant_id', 'category_id'], 'products_tenant_category_index');
            $table->index(['tenant_id', 'brand_id'], 'products_tenant_brand_index');
        });
        Schema::table('product_stocks', function (Blueprint $table): void {
            $table->index(['tenant_id', 'warehouse_id'], 'product_stocks_tenant_warehouse_index');
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['tenant_id', 'created_at'], 'stock_movements_tenant_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_tenant_id_index'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex('sales_tenant_warehouse_sold_index'));
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_tenant_category_index');
            $table->dropIndex('products_tenant_brand_index');
        });
        Schema::table('product_stocks', fn (Blueprint $table) => $table->dropIndex('product_stocks_tenant_warehouse_index'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_movements_tenant_created_index'));
    }
};
