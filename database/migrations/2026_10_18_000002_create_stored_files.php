<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Default storage allowance per plan, in megabytes. A platform admin can change each plan, or one shop, later. */
    private const STORAGE_MB = ['basic' => 100, 'medium' => 500, 'enterprise' => 2000];

    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('purpose', 30);
            $table->string('mime', 100)->nullable();
            // Bytes, the figure counted against the shop's storage allowance.
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['tenant_id', 'purpose']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('image_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
        });

        foreach (self::STORAGE_MB as $code => $megabytes) {
            $row = DB::table('subscription_plans')->where('code', $code)->first();

            if ($row === null) {
                continue;
            }

            $limits = (array) json_decode((string) $row->limits, true);

            if (! array_key_exists('storage_mb', $limits)) {
                $limits['storage_mb'] = $megabytes;
                DB::table('subscription_plans')->where('code', $code)->update(['limits' => json_encode($limits), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('image_file_id');
        });
        Schema::dropIfExists('stored_files');
    }
};
