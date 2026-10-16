<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // `domain` stays the active address. A domain a shop is still proving it owns waits here, so it can
            // never block the real owner of that name.
            $table->string('domain_pending')->nullable();
            $table->string('domain_token', 64)->nullable();
            $table->timestamp('domain_verified_at')->nullable();
            // Last time the name was seen pointing at this app. The shared /t/{slug} link only forwards after that.
            $table->timestamp('domain_routed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['domain_pending', 'domain_token', 'domain_verified_at', 'domain_routed_at']);
        });
    }
};
