<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Annual prices seeded earlier; changed to ten months only when nobody edited them since. */
    private const OLD_ANNUAL = ['basic' => 59_000_000, 'medium' => 82_000_000, 'enterprise' => 105_000_000];

    public function up(): void
    {
        foreach (DB::table('subscription_plans')->get() as $plan) {
            $features = (array) json_decode((string) $plan->features, true);

            if (in_array('integrations', $features, true)) {
                $features = array_values(array_diff($features, ['integrations']));
                $features = $plan->code === 'basic' ? [...$features, 'printer'] : [...$features, 'printer', 'mobile_money'];
            }

            if ($plan->code === 'basic') {
                $features[] = 'printer';
            }

            $update = ['features' => json_encode(array_values(array_unique($features))), 'updated_at' => now()];

            if (isset(self::OLD_ANNUAL[$plan->code], $plan->monthly_price) && (int) $plan->annual_price === self::OLD_ANNUAL[$plan->code]) {
                $update['annual_price'] = (int) $plan->monthly_price * 10;
            }

            DB::table('subscription_plans')->where('id', $plan->id)->update($update);
        }

        // A shop that was given "integrations" by hand keeps every channel it could use before.
        foreach (DB::table('tenants')->whereNotNull('settings')->get(['id', 'settings']) as $tenant) {
            $settings = json_decode((string) $tenant->settings, true);
            $features = $settings['plan_overrides']['features'] ?? null;

            if (! is_array($features) || ! in_array('integrations', $features, true)) {
                continue;
            }

            $settings['plan_overrides']['features'] = array_values(array_unique([
                ...array_diff($features, ['integrations']),
                'printer', 'mobile_money', 'fiscal',
            ]));

            DB::table('tenants')->where('id', $tenant->id)->update(['settings' => json_encode($settings)]);
        }
    }

    public function down(): void
    {
        // Not reversible: the old flag cannot be rebuilt without guessing.
    }
};
