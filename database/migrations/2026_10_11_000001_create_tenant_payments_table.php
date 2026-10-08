<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform billing records: what a shop paid us for its subscription. Not shop data, so no tenant scope.
        Schema::create('tenant_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('plan', 30)->nullable();
            // Integer minor units.
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 3)->default('TZS');
            $table->string('method', 30);
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('months');
            $table->date('paid_on');
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->index(['tenant_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
