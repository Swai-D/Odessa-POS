<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\Plans;
use App\Support\Tenancy\TenantContext;

function platformAdmin(): User
{
    return User::factory()->create(['is_super_admin' => true]);
}

function newShopPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Mama Lishe',
        'slug' => 'mama-lishe',
        'plan' => 'medium',
        'status' => 'active',
        'paid_until' => '2026-12-31',
        'owner_name' => 'Mama L',
        'owner_email' => 'mama@example.com',
        'owner_password' => 'secret-pass-1',
    ], $overrides);
}

it('keeps the shops page away from shop users and guests', function () {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->get('/platform/tenants')->assertRedirect('/login');
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/platform/tenants')->assertForbidden();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/platform/tenants', newShopPayload())->assertForbidden();
});

it('lets a super admin list shops and only shows the platform menu', function () {
    createTenant('shop-a');

    $html = $this->actingAs(platformAdmin())->get('/platform/tenants')->assertOk()->assertSee('Shop-a')->getContent();

    expect($html)->toContain(route('platform.tenants.index'))
        ->and($html)->not->toContain(route('products.index'));
});

it('creates a shop with an owner who can sign in to it', function () {
    $this->actingAs(platformAdmin())->post('/platform/tenants', newShopPayload())
        ->assertRedirect(route('platform.tenants.index'));

    $tenant = Tenant::query()->where('slug', 'mama-lishe')->firstOrFail();
    expect($tenant->plan)->toBe('medium')
        ->and($tenant->paid_until?->format('Y-m-d'))->toBe('2026-12-31');

    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'mama@example.com')->firstOrFail();
    expect($owner->tenant_id)->toBe($tenant->getKey())
        ->and($owner->is_super_admin)->toBeFalse();

    auth()->logout();
    $this->actingAs($owner)->withHeader('X-Tenant', 'mama-lishe')->get('/products')->assertOk();
    $this->actingAs($owner)->withHeader('X-Tenant', 'mama-lishe')->get('/suppliers')->assertOk();
});

it('validates a new shop', function () {
    createTenant('taken');

    $this->actingAs(platformAdmin())
        ->post('/platform/tenants', newShopPayload(['slug' => 'Bad Slug!', 'plan' => 'platinum', 'owner_password' => 'short']))
        ->assertSessionHasErrors(['slug', 'plan', 'owner_password']);

    $this->actingAs(platformAdmin())
        ->post('/platform/tenants', newShopPayload(['slug' => 'taken']))
        ->assertSessionHasErrors('slug');
});

it('changes plan, status and overrides of a shop', function () {
    $tenant = createTenant('shop-b', 'basic');

    $this->actingAs(platformAdmin())->put("/platform/tenants/{$tenant->getKey()}", [
        'name' => 'Shop B',
        'status' => 'suspended',
        'plan' => 'medium',
        'paid_until' => '2027-01-15',
        'override_features' => ['fiscal'],
        'limit_users' => 25,
    ])->assertRedirect(route('platform.tenants.index'));

    $tenant->refresh();
    $plans = app(TenantContext::class)->run($tenant, fn () => Plans::current());

    expect($tenant->status)->toBe('suspended')
        ->and($plans->key())->toBe('medium')
        ->and($plans->allows('fiscal'))->toBeTrue()
        ->and($plans->limit('users'))->toBe(25)
        ->and($plans->limit('warehouses'))->toBe(3);

    // Clearing the overrides returns the shop to its plan.
    $this->actingAs(platformAdmin())->put("/platform/tenants/{$tenant->getKey()}", [
        'name' => 'Shop B', 'status' => 'active', 'plan' => 'basic',
    ])->assertRedirect();

    $tenant->refresh();
    expect($tenant->settings['plan_overrides'] ?? null)->toBeNull();
});
