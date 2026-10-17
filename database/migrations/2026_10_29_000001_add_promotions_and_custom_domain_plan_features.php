<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Promotions start at the Medium plan: every plan that already includes mobile money (the Medium tier marker,
     * also true of copies of it) gets the feature, so no paying shop above Basic loses what it had. Plans with
     * every feature ("*") need nothing, which is also how custom domains are included (Enterprise only).
     */
    public function up(): void
    {
        foreach (DB::table('subscription_plans')->get() as $plan) {
            $features = (array) json_decode((string) $plan->features, true);

            if (in_array('*', $features, true) || in_array('promotions', $features, true) || ! in_array('mobile_money', $features, true)) {
                continue;
            }

            DB::table('subscription_plans')->where('id', $plan->id)->update([
                'features' => json_encode([...$features, 'promotions']),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('subscription_plans')->get() as $plan) {
            $features = array_values(array_diff((array) json_decode((string) $plan->features, true), ['promotions']));
            DB::table('subscription_plans')->where('id', $plan->id)->update(['features' => json_encode($features)]);
        }
    }
};
