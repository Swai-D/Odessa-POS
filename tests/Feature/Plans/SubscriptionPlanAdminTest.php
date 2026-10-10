<?php

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Plans;
use App\Support\Tenancy\TenantContext;

function subscriptionPlanPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'starter_plus',
        'name' => 'Starter Plus',
        'monthly_price' => '65000.00',
        'annual_price' => '650000.00',
        'features' => ['returns', 'reports'],
        'limit_users' => '7',
        'limit_warehouses' => '2',
        'is_active' => '1',
        'sort_order' => '15',
    ], $overrides);
}

it('seeds the configured initial monthly and annual prices for the standard plans', function (): void {
    $prices = SubscriptionPlan::query()->orderBy('code')->get()->keyBy('code');

    expect($prices['basic']->monthly_price)->toBe(5_000_000)
        ->and($prices['basic']->annual_price)->toBe(59_000_000)
        ->and($prices['medium']->monthly_price)->toBe(7_000_000)
        ->and($prices['medium']->annual_price)->toBe(82_000_000)
        ->and($prices['enterprise']->monthly_price)->toBe(9_000_000)
        ->and($prices['enterprise']->annual_price)->toBe(105_000_000);
});

it('lets super admins create and edit a priced plan with features and limits', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)->post(route('platform.plans.store'), subscriptionPlanPayload())
        ->assertRedirect(route('platform.plans.index'));

    $plan = SubscriptionPlan::query()->where('code', 'starter_plus')->firstOrFail();
    expect($plan->monthly_price)->toBe(6_500_000)
        ->and($plan->annual_price)->toBe(65_000_000)
        ->and($plan->features)->toBe(['returns', 'reports'])
        ->and($plan->limits)->toBe(['users' => 7, 'warehouses' => 2]);

    $update = subscriptionPlanPayload([
        'name' => 'Starter Plus Updated',
        'monthly_price' => '70000',
        'annual_price' => '700000',
        'features' => ['returns'],
        'limit_users' => '',
        'limit_warehouses' => '4',
    ]);
    unset($update['code']);
    $this->actingAs($admin)->put(route('platform.plans.update', $plan), $update)
        ->assertRedirect(route('platform.plans.index'));

    $plan->refresh();
    expect($plan->name)->toBe('Starter Plus Updated')
        ->and($plan->monthly_price)->toBe(7_000_000)
        ->and($plan->annual_price)->toBe(70_000_000)
        ->and($plan->features)->toBe(['returns'])
        ->and($plan->limits)->toBe(['users' => null, 'warehouses' => 4]);

    $tenant = createTenant('starter-plus-shop', 'starter_plus');
    $plans = app(TenantContext::class)->run($tenant, fn (): Plans => Plans::current());
    expect($plans->allows('returns'))->toBeTrue()
        ->and($plans->allows('reports'))->toBeFalse()
        ->and($plans->limit('users'))->toBeNull()
        ->and($plans->limit('warehouses'))->toBe(4);
});

it('uses active database plans for new shops and refuses to delete plans in use', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin)->post(route('platform.plans.store'), subscriptionPlanPayload())->assertRedirect();
    $plan = SubscriptionPlan::query()->where('code', 'starter_plus')->firstOrFail();

    $tenantPayload = [
        'name' => 'Starter Shop', 'slug' => 'starter-shop', 'plan' => 'starter_plus', 'status' => 'active',
        'owner_name' => 'Starter Owner', 'owner_email' => 'starter@example.com', 'owner_password' => 'secret-pass-1',
    ];
    $this->actingAs($admin)->post(route('platform.tenants.store'), $tenantPayload)
        ->assertRedirect(route('platform.tenants.index'));

    $tenant = Tenant::query()->where('slug', 'starter-shop')->firstOrFail();
    $this->actingAs($admin)->delete(route('platform.plans.destroy', $plan))
        ->assertRedirect(route('platform.plans.index'))
        ->assertSessionHasErrors('plan');
    expect($plan->fresh())->not->toBeNull()->and($tenant->plan)->toBe('starter_plus');

    $inactiveUpdate = subscriptionPlanPayload(['is_active' => '0']);
    unset($inactiveUpdate['code']);
    $this->actingAs($admin)->put(route('platform.plans.update', $plan), $inactiveUpdate)
        ->assertRedirect(route('platform.plans.index'));
    $this->actingAs($admin)->post(route('platform.tenants.store'), [
        ...$tenantPayload,
        'name' => 'Inactive Plan Shop',
        'slug' => 'inactive-plan-shop',
        'owner_email' => 'inactive@example.com',
    ])->assertSessionHasErrors('plan');
});

it('protects plan management from guests and shop users', function (): void {
    $tenant = createTenant('plan-access-shop');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->get(route('platform.plans.index'))->assertRedirect('/login');
    $this->actingAs($user)->withHeader('X-Tenant', 'plan-access-shop')
        ->get(route('platform.plans.index'))->assertForbidden();
    $this->actingAs($user)->withHeader('X-Tenant', 'plan-access-shop')
        ->post(route('platform.plans.store'), subscriptionPlanPayload())->assertForbidden();
});
