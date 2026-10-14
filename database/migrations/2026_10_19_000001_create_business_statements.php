<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 30);
            // Shown on the document and typed in on the public verify page. Unique across all shops.
            $table->string('code', 20)->unique();
            $table->string('shop_name', 190);
            $table->string('currency', 8);
            $table->string('locale', 5);
            $table->unsignedTinyInteger('months');
            $table->date('period_from');
            $table->date('period_to');
            // The figures exactly as issued; a statement is never recalculated or edited afterwards.
            $table->json('data');
            // Keyed hash over the figures and identity, to notice a record that was altered after issue.
            $table->string('checksum', 64);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_statements');
    }
};
