<?php

use App\Domain\Settings\Services\OnboardingChecklist;
use App\Support\Tenancy\TenantContext;

function onboardingSteps(object $tenant): array
{
    return app(TenantContext::class)->run($tenant, fn () => collect(app(OnboardingChecklist::class)->steps())->pluck('done', 'key')->all());
}

it('starts with nothing done for a brand new shop', function () {
    $tenant = createTenant('fresh');

    expect(onboardingSteps($tenant))->toBe(['details' => false, 'products' => false, 'stock' => false, 'staff' => false, 'sale' => false]);
});

it('ticks steps from real data', function () {
    $tenant = createTenant('growing');
    $owner = createTenantUser($tenant, [...posPermissions(), 'settings.manage']);
    $tenant->update(['settings' => ['business_name' => 'Mama Shop']]);
    [$product, $warehouse] = posFixture($tenant);

    expect(onboardingSteps($tenant->refresh()))->toMatchArray(['details' => true, 'products' => true, 'stock' => true, 'staff' => false, 'sale' => false]);

    createTenantUser($tenant, ['sales.view']);
    checkout($this, 'growing', $owner, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertSuccessful();

    expect(onboardingSteps($tenant->refresh()))->toMatchArray(['staff' => true, 'sale' => true]);
});

it('shows the checklist to the owner on the dashboard', function () {
    $tenant = createTenant('owner-shop');
    $owner = createTenantUser($tenant, ['sales.view', 'settings.manage']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'owner-shop')->get('/dashboard')
        ->assertOk()
        ->assertSee('data-onboarding', false)
        ->assertSee('0 of 5 steps done');
});

it('does not show the checklist to staff without settings access', function () {
    $tenant = createTenant('staff-shop');
    $cashier = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($cashier)->withHeader('X-Tenant', 'staff-shop')->get('/dashboard')
        ->assertOk()
        ->assertDontSee('data-onboarding', false);
});

it('speaks Swahili', function () {
    $tenant = createTenant('sw-shop');
    $owner = createTenantUser($tenant, ['sales.view', 'settings.manage']);
    $owner->update(['locale' => 'sw']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'sw-shop')->get('/dashboard')
        ->assertSee('Andaa duka lako');
});

it('lets the owner hide the checklist and keeps it hidden', function () {
    $tenant = createTenant('hide-shop');
    $owner = createTenantUser($tenant, ['sales.view', 'settings.manage']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'hide-shop')->post('/onboarding/dismiss')->assertRedirect(route('dashboard'));

    expect($tenant->refresh()->settings['onboarding_dismissed'])->toBeTrue();
    $this->actingAs($owner)->withHeader('X-Tenant', 'hide-shop')->get('/dashboard')->assertDontSee('data-onboarding', false);
});

it('refuses to hide the checklist without settings access', function () {
    $tenant = createTenant('deny-shop');
    $cashier = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($cashier)->withHeader('X-Tenant', 'deny-shop')->post('/onboarding/dismiss')->assertForbidden();

    expect($tenant->refresh()->settings['onboarding_dismissed'] ?? false)->toBeFalse();
});

it('hides itself once every step is done', function () {
    $tenant = createTenant('done-shop');
    $owner = createTenantUser($tenant, [...posPermissions(), 'settings.manage']);
    $tenant->update(['settings' => ['business_name' => 'Done']]);
    [$product, $warehouse] = posFixture($tenant);
    createTenantUser($tenant, ['sales.view']);
    checkout($this, 'done-shop', $owner, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertSuccessful();

    $this->actingAs($owner)->withHeader('X-Tenant', 'done-shop')->get('/dashboard')->assertDontSee('data-onboarding', false);
});
