<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A mobile money prompt sent to a customer's phone. The sale is created by the normal checkout once the
        // request has succeeded; `sale_id` marks the request as used, so one payment can never pay two sales.
        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('idempotency_key', 64);
            // Our reference, sent to the provider as externalId and returned to us as utilityref.
            $table->string('reference', 64);
            $table->string('driver', 30)->default('azampay');
            $table->string('provider', 20);
            $table->string('phone', 20);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 20)->default('pending');
            $table->string('provider_reference')->nullable();
            $table->string('mno_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('callback')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
