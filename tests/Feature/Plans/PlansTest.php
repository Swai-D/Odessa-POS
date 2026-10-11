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
})->with(['/brands', '/purchases', '/suppliers', '/reports']);

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

it('lets Basic shops use the receipt printer but not mobile money or fiscal', function () {
    [$tenant, $user] = planTenant('basic');
    $plans = plansFor($tenant);

    expect($plans->allows('printer'))->toBeTrue()
        ->and($plans->allows('mobile_money'))->toBeFalse()
        ->and($plans->allows('fiscal'))->toBeFalse()
        ->and($plans->allowsAny('mobile_money', 'printer'))->toBeTrue()
        ->and($plans->allowsAny('mobile_money', 'fiscal'))->toBeFalse();

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'shop-basic')->get('/settings/integrations')->assertOk();
    expect($page->getContent())->toContain(__('plans.available_from', ['plan' => 'Medium']));
});

it('refuses to save a locked channel on Basic', function () {
    [, $user] = planTenant('basic');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-basic')
        ->put('/settings/integrations/payments', ['enabled' => '0'])->assertForbidden();
});

it('lets a Medium shop use mobile money but not fiscal', function () {
    [$tenant] = planTenant('medium');
    $plans = plansFor($tenant);

    expect($plans->allows('mobile_money'))->toBeTrue()->and($plans->allows('fiscal'))->toBeFalse();
});

it('gives a shop on trial the Medium features whatever its plan row says', function () {
    $tenant = createTenant('shop-trial', 'basic');
    $tenant->update(['status' => 'trial', 'trial_ends_at' => now()->addDays(5)]);
    $plans = plansFor($tenant->fresh());

    expect($plans->allows('purchasing'))->toBeTrue()
        ->and($plans->allows('expenses'))->toBeTrue()
        ->and($plans->allows('fiscal'))->toBeFalse();

    $tenant->update(['status' => 'active']);
    expect(plansFor($tenant->fresh())->allows('purchasing'))->toBeFalse();
});

it('shows the net profit card locked on Basic and live on Medium', function () {
    $basic = createTenant('shop-basic', 'basic');
    $basicUser = createTenantUser($basic, ['dashboard.view', 'sales.view', 'expenses.view']);
    $html = $this->actingAs($basicUser)->withHeader('X-Tenant', 'shop-basic')->get('/dashboard')->assertOk()->getContent();
    expect($html)->toContain('data-locked-metric="net_profit"');

    $medium = createTenant('shop-medium', 'medium');
    $mediumUser = createTenantUser($medium, ['dashboard.view', 'sales.view', 'expenses.view']);
    $html = $this->actingAs($mediumUser)->withHeader('X-Tenant', 'shop-medium')->get('/dashboard')->assertOk()->getContent();
    expect($html)->not->toContain('data-locked-metric');
});
