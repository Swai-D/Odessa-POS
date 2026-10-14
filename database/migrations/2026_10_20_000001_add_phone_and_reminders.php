<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->after('email');
        });

        // One row per reminder actually sent, so the daily job can run twice and still send once.
        Schema::create('subscription_reminders', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 26)->index();
            $table->string('kind', 20);
            $table->date('deadline');
            $table->timestamps();
            $table->unique(['tenant_id', 'kind', 'deadline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
