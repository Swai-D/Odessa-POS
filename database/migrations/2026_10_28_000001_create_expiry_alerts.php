<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The last expiry digest sent per shop, so an unchanged list is not repeated every morning.
        Schema::create('expiry_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 26)->unique();
            $table->string('signature', 64);
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expiry_alerts');
    }
};
