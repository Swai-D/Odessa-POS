<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('driver', 50);
            // Plain settings (paper width, shortcode...) and encrypted secrets (keys, passwords, tokens).
            $table->json('settings')->nullable();
            $table->text('secrets')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_integrations');
    }
};
