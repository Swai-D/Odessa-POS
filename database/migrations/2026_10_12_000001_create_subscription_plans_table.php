<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('monthly_price')->nullable();
            $table->unsignedBigInteger('annual_price')->nullable();
            $table->json('features');
            $table->json('limits');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $definitions = (array) config('plans.plans', []);
        $resolve = static function (string $code, array $trail = []) use (&$resolve, $definitions): array {
            $definition = (array) ($definitions[$code] ?? []);
            $parent = (string) ($definition['inherits'] ?? '');
            $inherited = $parent !== '' && ! in_array($parent, $trail, true)
                ? $resolve($parent, [...$trail, $code])
                : ['features' => [], 'limits' => []];

            return [
                'features' => array_values(array_unique([...$inherited['features'], ...(array) ($definition['features'] ?? [])])),
                'limits' => array_merge($inherited['limits'], (array) ($definition['limits'] ?? [])),
            ];
        };

        $now = now();
        $rows = [];
        foreach (array_keys($definitions) as $sortOrder => $code) {
            $resolved = $resolve((string) $code);
            $rows[] = [
                'code' => (string) $code,
                'name' => ucfirst(str_replace(['-', '_'], ' ', (string) $code)),
                'monthly_price' => null,
                'annual_price' => null,
                'features' => json_encode($resolved['features'], JSON_THROW_ON_ERROR),
                'limits' => json_encode($resolved['limits'], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('subscription_plans')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
