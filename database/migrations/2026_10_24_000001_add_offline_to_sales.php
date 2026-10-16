<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            // Set when the till sold this offline and synced it later. `review_reason` lists what the owner should
            // look at (stock, price, payment, credit); `reviewed_at` clears it.
            $table->timestamp('offline_synced_at')->nullable()->after('sold_at');
            $table->string('review_reason', 60)->nullable()->after('offline_synced_at');
            $table->timestamp('reviewed_at')->nullable()->after('review_reason');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'review_reason', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'review_reason', 'reviewed_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['offline_synced_at', 'review_reason', 'reviewed_at']);
        });
    }
};
