<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Integer minor units.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->string('method', 30)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->date('spent_on');
            $table->timestamps();

            $table->index(['tenant_id', 'spent_on']);
            $table->index(['tenant_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
