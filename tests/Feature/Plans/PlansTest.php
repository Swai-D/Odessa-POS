<?php

use App\Support\Plans;
use App\Support\Tenancy\TenantContext;

function planTenant(string $plan, array $settings = []): array
{
    $tenant = createTenant('shop-'.$plan, $plan);
    if ($settings !== []) {
        $tenant->update(['settings' => $settings]);
    }
    $user = createTenantUser($tenant, [
        'brands.view', 'purchases.view', 'suppliers.view', 'reports.view', 'settings.manage', 'dashboard.view',
    ]);

    return [$tenant, $user];
}

function plansFor($tenant): Plans
{
    return app(TenantContext::class)->run($tenant, fn () => Plans::current());
}

it('blocks medium features on the basic plan', function (string $url) {
    [, $user] = planTenant('basic');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-basic')->get($url)->assertForbidden();
})->with(['/brands', '/purchases', '/suppliers', '/reports', '/settings/integrations']);

it('allows those features on medium and enterprise', function (string $plan) {
    [, $user] = planTenant($plan);

    foreach (['/brands', '/purchases', '/suppliers', '/reports', '/settings/integrations'] as $url) {
        $this->actingAs($user)->withHeader('X-Tenant', 'shop-'.$plan)->get($url)->assertOk();
    }
})->with(['medium', 'enterprise']);

it('shows gated items in the basic sidebar as locked, not as normal links', function () {
    [, $user] = planTenant('basic');

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-basic')->get('/dashboard')->assertOk()->getContent();

    foreach (['purchases.index', 'brands.index', 'reports.index'] as $name) {
        expect($html)->toContain('<a href="'.route($name).'" class="text-muted" data-locked="1"')
            ->and($html)->not->toContain('<a href="'.route($name).'" class="active"');
    }
});

it('does not lock items the plan includes', function () {
    [, $user] = planTenant('medium');

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-medium')->get('/dashboard')->assertOk()->getContent();

    expect($html)->not->toContain('data-locked');
});

it('names the plan that unlocks a feature on the upgrade page', function () {
    [, $user] = planTenant('basic');
    config(['plans.contact' => ['phone' => '+255700000000', 'email' => null]]);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-basic')->get('/reports')
        ->assertForbidden()->assertSee('Available from the Medium plan')->assertSee('+255700000000');
});

it('knows the smallest plan for each feature', function () {
    expect(Plans::cheapestPlanFor('returns'))->toBe('basic')
        ->and(Plans::cheapestPlanFor('reports'))->toBe('medium')
        ->and(Plans::cheapestPlanFor('expenses'))->toBe('medium')
        ->and(Plans::cheapestPlanFor('fiscal'))->toBe('enterprise')
        ->and(Plans::cheapestPlanFor('nope'))->toBeNull();
});

it('resolves limits, aliases and overrides', function () {
    $basic = createTenant('limits-basic', 'basic');
    $demo = createTenant('limits-demo', 'demo');
    $custom = createTenant('limits-custom', 'basic');
    $custom->update(['settings' => ['plan_overrides' => ['features' => ['reports'], 'limits' => ['users' => 50]]]]);

    expect(plansFor($basic)->limit('users'))->toBe(3)
        ->and(plansFor($basic)->allows('reports'))->toBeFalse()
        ->and(plansFor($demo)->key())->toBe('enterprise')
        ->and(plansFor($demo)->limit('users'))->toBeNull()
        ->and(plansFor($custom)->allows('reports'))->toBeTrue()
        ->and(plansFor($custom)->limit('users'))->toBe(50);
});

it('restricts nothing outside a shop', function () {
    expect(Plans::current()->allows('fiscal'))->toBeTrue();
});
