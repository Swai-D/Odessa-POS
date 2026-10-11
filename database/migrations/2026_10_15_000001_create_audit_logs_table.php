<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Who did it. The name is copied so the entry still reads well after the user is removed.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable();
            $table->string('ip_address', 45)->nullable();
            // created, updated, deleted or a named action such as role_changed.
            $table->string('event', 40);
            // Short class name of the record ("Product", "Sale"...), its id and a readable label ("INV-0007").
            $table->string('subject_type', 60);
            $table->string('subject_id', 40)->nullable();
            $table->string('subject_label', 190)->nullable();
            // {"field": {"old": ..., "new": ...}}
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
