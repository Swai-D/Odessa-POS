<?php

use App\Models\Tenant;
use App\Models\User;

function signupForm(array $override = []): array
{
    return array_merge([
        'shop_name' => 'Mama Neema Store',
        'owner_name' => 'Neema Joseph',
        'email' => 'Neema@Example.test',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'terms' => '1',
        'website' => '',
    ], $override);
}

it('shows the sign-up page and links to it from sign in', function (): void {
    $this->get('/register')->assertOk()->assertSee('Start free trial')->assertSee('/terms', false);
    $this->get('/login')->assertOk()->assertSee('Start a free trial');
});

it('opens a trial shop with its owner and signs them in', function (): void {
    $this->post('/register', signupForm())->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $tenant = Tenant::query()->where('slug', 'mama-neema-store')->firstOrFail();
    expect($tenant->name)->toBe('Mama Neema Store')
        ->and($tenant->status)->toBe('trial')
        ->and($tenant->plan)->toBe(config('plans.trial_plan'))
        ->and($tenant->trial_ends_at)->not->toBeNull()
        ->and($tenant->trial_ends_at->isFuture())->toBeTrue()
        ->and($tenant->paid_until)->toBeNull();

    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'neema@example.test')->firstOrFail();
    expect($owner->tenant_id)->toBe($tenant->getKey())->and($owner->is_super_admin)->toBeFalse();

    $this->get('/dashboard')->assertOk()->assertSee('Neema Joseph');
});

it('gives the owner the Owner role, a main warehouse and units', function (): void {
    $this->post('/register', signupForm())->assertRedirect();

    $tenant = Tenant::query()->firstOrFail();
    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'neema@example.test')->firstOrFail();

    app(App\Support\Tenancy\TenantContext::class)->run($tenant, function () use ($tenant, $owner): void {
        app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());

        expect($owner->hasRole('Owner'))->toBeTrue()
            ->and(App\Domain\Inventory\Models\Warehouse::query()->where('is_default', true)->count())->toBe(1)
            ->and(App\Domain\Catalog\Models\Unit::query()->count())->toBeGreaterThan(0);
    });
});

it('keeps the language the visitor was using', function (): void {
    $this->withSession(['locale' => 'sw'])->post('/register', signupForm())->assertRedirect();

    $owner = User::query()->withoutGlobalScope('tenant')->where('email', 'neema@example.test')->firstOrFail();
    expect($owner->locale)->toBe('sw')->and(Tenant::query()->firstOrFail()->settings['locale'])->toBe('sw');
});

it('makes the shop address unique when the name is taken or reserved', function (): void {
    createTenant('mama-neema-store');

    $this->post('/register', signupForm())->assertRedirect();

    $slug = Tenant::query()->where('slug', '!=', 'mama-neema-store')->firstOrFail()->slug;
    expect($slug)->toStartWith('mama-neema-store-')->and(Tenant::query()->count())->toBe(2);

    auth()->logout();
    $this->flushSession();
    $this->post('/register', signupForm(['shop_name' => 'Admin', 'email' => 'x@example.test']))->assertRedirect();

    expect(Tenant::query()->where('slug', 'like', 'admin%')->exists())->toBeFalse();
});

it('refuses an email that already has an account and does not touch the other shop', function (): void {
    $existing = createTenant('old-shop');
    loginUser($existing->getKey(), 'neema@example.test');

    $this->post('/register', signupForm())->assertSessionHasErrors('email');

    expect(Tenant::query()->count())->toBe(1)
        ->and(User::query()->withoutGlobalScope('tenant')->where('email', 'neema@example.test')->value('tenant_id'))->toBe($existing->getKey());
});

it('validates the form', function (): void {
    $this->post('/register', signupForm(['password_confirmation' => 'different']))->assertSessionHasErrors('password');
    $this->post('/register', signupForm(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
    $this->post('/register', signupForm(['terms' => null]))->assertSessionHasErrors('terms');
    $this->post('/register', signupForm(['shop_name' => '']))->assertSessionHasErrors('shop_name');
    $this->post('/register', signupForm(['email' => 'not-an-email']))->assertSessionHasErrors('email');

    expect(Tenant::query()->count())->toBe(0);
});

it('quietly ignores a bot that fills the hidden field', function (): void {
    $this->post('/register', signupForm(['website' => 'http://spam.test']))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(Tenant::query()->count())->toBe(0);
});

it('limits how many shops one address can open in an hour', function (): void {
    foreach (range(1, 5) as $i) {
        $this->post('/register', signupForm(['shop_name' => 'Shop '.$i, 'email' => "s{$i}@example.test"]))->assertRedirect();
        auth()->logout();
        $this->flushSession();
    }

    $this->post('/register', signupForm(['shop_name' => 'Shop 6', 'email' => 's6@example.test']))->assertStatus(429);

    expect(Tenant::query()->count())->toBe(5);
});

it('is switched off with SELF_SIGNUP_ENABLED', function (): void {
    config(['plans.self_signup' => false]);

    $this->get('/register')->assertNotFound();
    $this->post('/register', signupForm())->assertNotFound();
    $this->get('/login')->assertOk()->assertDontSee('Start a free trial');

    expect(Tenant::query()->count())->toBe(0);
});

it('is not offered to someone who is already signed in', function (): void {
    $tenant = createTenant('shop-r1');
    $user = loginUser($tenant->getKey(), 'owner@r1.test');

    $this->actingAs($user)->get('/register')->assertRedirect();
});

it('is not available on a shop\'s own address', function (): void {
    createTenant('shop-r2');

    $this->get('http://shop-r2.localhost/register')->assertNotFound();
});
