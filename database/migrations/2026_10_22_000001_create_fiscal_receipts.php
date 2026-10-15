<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per sale reported to TRA. The XML is frozen when the receipt is issued: a resend must carry the
        // original content, date and time, never a rebuilt copy.
        Schema::create('fiscal_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedBigInteger('gc');
            $table->unsignedInteger('dc');
            $table->unsignedInteger('znum');
            $table->string('rctvnum', 40);
            $table->timestamp('issued_at');
            $table->longText('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('ack_code', 10)->nullable();
            $table->string('ack_message')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sale_id']);
            $table->unique(['tenant_id', 'gc']);
            $table->unique(['tenant_id', 'znum', 'dc']);
            $table->index(['tenant_id', 'status', 'gc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
