<?php

namespace App\Domain\Settings\Actions;

use App\Models\SubscriptionPlan;
use App\Support\Money;

class SaveSubscriptionPlanAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?SubscriptionPlan $plan = null): SubscriptionPlan
    {
        $plan ??= new SubscriptionPlan;
        $plan->fill([
            'code' => $data['code'] ?? $plan->code,
            'name' => $data['name'],
            'monthly_price' => Money::toMinor($data['monthly_price']),
            'annual_price' => Money::toMinor($data['annual_price']),
            'features' => array_values((array) ($data['features'] ?? [])),
            'limits' => [
                'users' => isset($data['limit_users']) ? (int) $data['limit_users'] : null,
                'warehouses' => isset($data['limit_warehouses']) ? (int) $data['limit_warehouses'] : null,
            ],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) $data['sort_order'],
        ]);
        $plan->save();

        return $plan;
    }
}
