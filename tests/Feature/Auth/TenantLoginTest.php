<?php

use App\Models\User;

function loginUser(?string $tenantId, string $email): User
{
    return User::factory()->create(['tenant_id' => $tenantId, 'email' => $email]);
}

it('signs a shop user in when no shop is resolved yet (local address)', function (): void {
    $tenant = createTenant('shop-l1');
    loginUser($tenant->getKey(), 'owner@l1.test');

    $this->post('/login', ['email' => 'owner@l1.test', 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticated();
});

it('signs a shop user in on their own shop address', function (): void {
    $tenant = createTenant('shop-l2');
    loginUser($tenant->getKey(), 'owner@l2.test');

    $this->withHeader('X-Tenant', 'shop-l2')->post('/login', ['email' => 'owner@l2.test', 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticated();
});

it('refuses a user of another shop on this shop\'s address', function (): void {
    $a = createTenant('shop-l3');
    createTenant('shop-l3b');
    loginUser($a->getKey(), 'owner@l3.test');

    $this->withHeader('X-Tenant', 'shop-l3b')->post('/login', ['email' => 'owner@l3.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuses a wrong password', function (): void {
    $tenant = createTenant('shop-l4');
    loginUser($tenant->getKey(), 'owner@l4.test');

    $this->post('/login', ['email' => 'owner@l4.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('signs a shop user in at the shared address even when the remembered shop is another one', function (): void {
    $mine = createTenant('shop-m1');
    createTenant('shop-m2');
    loginUser($mine->getKey(), 'owner@m1.test');

    $this->withSession(['tenant_slug' => 'shop-m2'])->post('/login', ['email' => 'owner@m1.test', 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticated();
    expect(session('tenant_slug'))->toBe('shop-m1');
});

it('remembers the shop for the session after signing in with no shop chosen (session expired)', function (): void {
    $tenant = createTenant('shop-m3');
    loginUser($tenant->getKey(), 'owner@m3.test');

    $this->post('/login', ['email' => 'owner@m3.test', 'password' => 'password'])->assertRedirect();

    expect(session('tenant_slug'))->toBe('shop-m3');
});

it('lets a platform admin sign in and forgets a shop left over from an earlier visit', function (): void {
    createTenant('shop-m4');
    User::factory()->create(['tenant_id' => null, 'is_super_admin' => true, 'email' => 'admin@m4.test']);

    $this->withSession(['tenant_slug' => 'shop-m4'])->post('/login', ['email' => 'admin@m4.test', 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticated();
    expect(session()->has('tenant_slug'))->toBeFalse();
});

it('still keeps a shop user out of another shop\'s own address', function (): void {
    $a = createTenant('shop-m5');
    createTenant('shop-m6');
    loginUser($a->getKey(), 'owner@m5.test');

    $this->withHeader('X-Tenant', 'shop-m6')->withSession(['tenant_slug' => 'shop-m5'])
        ->post('/login', ['email' => 'owner@m5.test', 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('sends a shop user back to their shop\'s sign-in page after signing out', function (): void {
    $tenant = createTenant('shop-m7');
    $user = loginUser($tenant->getKey(), 'owner@m7.test');

    $this->actingAs($user)->post('/logout')->assertRedirect(route('tenant.entry', 'shop-m7'));
});

it('offers a way out of a remembered shop and clears it', function (): void {
    createTenant('shop-m8');

    $this->withSession(['tenant_slug' => 'shop-m8'])->get('/login')->assertOk()->assertSee(route('tenant.leave'), false);
    $this->withSession(['tenant_slug' => 'shop-m8'])->get('/switch-shop')->assertRedirect(route('login'));
    expect(session()->has('tenant_slug'))->toBeFalse();
});
