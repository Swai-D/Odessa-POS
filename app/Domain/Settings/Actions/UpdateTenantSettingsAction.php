<?php

namespace App\Domain\Settings\Actions;

use App\Models\Tenant;

/** Saves the tenant's business settings, keeping any settings keys this form does not own. */
class UpdateTenantSettingsAction
{
    /** @param  array<string, mixed>  $data  validated SettingsRequest data */
    public function handle(Tenant $tenant, array $data): Tenant
    {
        $settings = $tenant->settings ?? [];

        $features = [];
        foreach (array_keys(config('pos.features')) as $feature) {
            $features[$feature] = (bool) ($data['features'][$feature] ?? false);
        }

        $settings['business_name'] = $data['business_name'];
        $settings['currency'] = $data['currency'];
        $settings['receipt_footer'] = $data['receipt_footer'] ?? null;
        $settings['features'] = array_merge($settings['features'] ?? [], $features);

        $tenant->update(['settings' => $settings]);

        return $tenant;
    }
}
