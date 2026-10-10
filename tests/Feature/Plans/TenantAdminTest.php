<?php

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Support\Plans;
use App\Support\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        ->and($html)->toContain('data-bs-target="#delete-modal"')
        ->and($html)->toContain('data-delete-message="'.e(__('platform.confirm_delete_shop')).'"')
        ->and($html)->toContain('class="action-icon d-inline-flex align-items-center"')
        ->and($html)->not->toContain('onsubmit="return confirm(')
        ->and($html)->not->toContain(route('products.index'));
});

it('lets a super admin view a shop from the shops list', function () {
    $tenant = createTenant('shop-a');

    $this->actingAs(platformAdmin())
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertSee(route('platform.tenants.show', $tenant));

    $this->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee($tenant->name)
        ->assertSee($tenant->slug)
        ->assertSee(__('platform.details'))
        ->assertSee(route('platform.tenants.edit', $tenant));
});

it('shows and updates a shop owner from the platform', function () {
    $admin = platformAdmin();

    $this->actingAs($admin)->post(route('platform.tenants.store'), newShopPayload())
        ->assertRedirect(route('platform.tenants.index'));

    $tenant = Tenant::query()->where('slug', 'mama-lishe')->firstOrFail();

    $this->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee('mama@example.com');
    $this->get(route('platform.tenants.edit', $tenant))
        ->assertOk()
        ->assertSee('value="mama@example.com"', false);

    $this->put(route('platform.tenants.update', $tenant), [
        'name' => 'Mama Lishe',
        'status' => 'active',
        'plan' => 'medium',
        'paid_until' => '2026-12-31',
        'owner_name' => 'Updated Owner',
        'owner_email' => 'updated-owner@example.com',
        'owner_password' => 'updated-owner-pass',
    ])->assertRedirect(route('platform.tenants.index'));

    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'updated-owner@example.com')->firstOrFail();

    expect($owner->name)->toBe('Updated Owner')
        ->and($owner->tenant_id)->toBe($tenant->getKey())
        ->and(Hash::check('updated-owner-pass', $owner->password))->toBeTrue();

    $this->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee('updated-owner@example.com')
        ->assertDontSee('mama@example.com');
});

it('can add an owner to an existing shop that has none', function () {
    $tenant = createTenant('ownerless-shop');

    $this->actingAs(platformAdmin())->put(route('platform.tenants.update', $tenant), [
        'name' => $tenant->name,
        'status' => 'active',
        'plan' => 'enterprise',
        'owner_name' => 'New Owner',
        'owner_email' => 'new-owner@example.com',
        'owner_password' => 'new-owner-pass',
    ])->assertRedirect(route('platform.tenants.index'));

    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'new-owner@example.com')->firstOrFail();

    expect($owner->name)->toBe('New Owner')
        ->and($owner->tenant_id)->toBe($tenant->getKey());
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

it('creates new shops on a dated trial and grants grace only after the trial ends', function (): void {
    Carbon::setTestNow('2026-10-10 12:00:00');
    $admin = platformAdmin();

    $this->actingAs($admin)->get('/platform/tenants/create')
        ->assertOk()
        ->assertSee('value="trial" selected', false)
        ->assertSee('name="trial_ends_at"', false)
        ->assertSee('value="2026-10-24"', false)
        ->assertSee('14 days to try the system; the 7-day grace period starts after this date.');

    $this->actingAs($admin)->post('/platform/tenants', newShopPayload([
        'name' => 'Trial Shop',
        'slug' => 'trial-shop',
        'status' => 'trial',
        'paid_until' => null,
        'owner_email' => 'trial@example.com',
    ]))->assertRedirect(route('platform.tenants.index'));

    $tenant = Tenant::query()->where('slug', 'trial-shop')->firstOrFail();
    expect($tenant->status)->toBe('trial')
        ->and($tenant->paid_until)->toBeNull()
        ->and($tenant->trial_ends_at?->format('Y-m-d'))->toBe('2026-10-24')
        ->and((new Subscription($tenant, Carbon::parse('2026-10-24 23:59:59')))->state())->toBe(Subscription::ACTIVE)
        ->and((new Subscription($tenant, Carbon::parse('2026-10-25 00:00:00')))->state())->toBe(Subscription::GRACE)
        ->and((new Subscription($tenant, Carbon::parse('2026-11-01 00:00:00')))->state())->toBe(Subscription::READONLY);

    Carbon::setTestNow();
});

it('soft deletes a shop, disables its access, and keeps its subscription payment history', function (): void {
    $tenant = createTenant('removed-shop');
    $tenant->update(['name' => 'Removed Shop']);
    $owner = createTenantUser($tenant, ['dashboard.view']);
    $today = Carbon::today();

    TenantPayment::query()->create([
        'tenant_id' => $tenant->getKey(),
        'idempotency_key' => (string) Str::uuid(),
        'plan' => 'basic',
        'plan_name' => 'Basic',
        'amount' => 5_000_000,
        'currency' => 'TZS',
        'method' => 'cash',
        'reference' => 'KEEP-LEDGER',
        'months' => 1,
        'paid_on' => $today->toDateString(),
        'period_start' => $today->toDateString(),
        'period_end' => $today->copy()->addMonth()->toDateString(),
    ]);

    $this->actingAs(platformAdmin())->delete(route('platform.tenants.destroy', $tenant))
        ->assertRedirect(route('platform.tenants.index'))
        ->assertSessionHas('status', __('platform.deleted'));

    expect(Tenant::withTrashed()->findOrFail($tenant->getKey())->trashed())->toBeTrue()
        ->and(TenantPayment::query()->where('reference', 'KEEP-LEDGER')->exists())->toBeTrue();

    $this->actingAs(platformAdmin())->get(route('platform.tenants.index'))->assertDontSee('Removed Shop');
    $this->actingAs(platformAdmin())->get(route('platform.payments.index'))->assertOk()
        ->assertSee('Removed Shop')->assertSee('KEEP-LEDGER');
    $this->actingAs($owner)->withHeader('X-Tenant', 'removed-shop')->get('/dashboard')->assertNotFound();
});

it('validates a new shop', function () {
    createTenant('taken');

    $this->actingAs(platformAdmin())
        ->post('/platform/tenants', newShopPayload(['slug' => 'Bad Slug!', 'plan' => 'platinum', 'owner_password' => 'short']))
        ->assertSessionHasErrors(['slug', 'plan', 'owner_password']);

    $this->actingAs(platformAdmin())
        ->post('/platform/tenants', newShopPayload(['slug' => 'taken']))
        ->assertSessionHasErrors('slug');

    $this->actingAs(platformAdmin())
        ->post('/platform/tenants', newShopPayload([
            'slug' => 'active-without-payment', 'status' => 'active', 'paid_until' => null,
        ]))->assertSessionHasErrors('paid_until');
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
