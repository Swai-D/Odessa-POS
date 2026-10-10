<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prices = [
            'basic' => ['monthly_price' => 5_000_000, 'annual_price' => 59_000_000],
            'medium' => ['monthly_price' => 7_000_000, 'annual_price' => 82_000_000],
            'enterprise' => ['monthly_price' => 9_000_000, 'annual_price' => 105_000_000],
        ];

        foreach ($prices as $code => $amounts) {
            DB::table('subscription_plans')->where('code', $code)->update($amounts + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('subscription_plans')->whereIn('code', ['basic', 'medium', 'enterprise'])
            ->update(['monthly_price' => null, 'annual_price' => null, 'updated_at' => now()]);
    }
};
