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
