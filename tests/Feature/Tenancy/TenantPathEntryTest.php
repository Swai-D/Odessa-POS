<?php

use App\Models\User;

it('selects an active tenant from a path on the Railway app domain', function (): void {
    $tenant = createTenant('demo-path');
    $user = User::factory()->create([
        'tenant_id' => $tenant->getKey(),
        'email' => 'path-owner@example.test',
    ]);
    config(['app.domain' => 'odessa-pos-production.up.railway.app']);
    config(['app.url' => 'https://odessa-pos-production.up.railway.app']);

    $this->get('https://odessa-pos-production.up.railway.app/t/demo-path')->assertRedirect('https://odessa-pos-production.up.railway.app/login');
    $this->post('https://odessa-pos-production.up.railway.app/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    $this->get('https://odessa-pos-production.up.railway.app/dashboard')->assertOk()->assertSee('Welcome, '.$user->name);
});

it('does not let a signed-in tenant user switch tenants through the path entry', function (): void {
    $tenant = createTenant('demo-path-a');
    $otherTenant = createTenant('demo-path-b');
    $user = User::factory()->create(['tenant_id' => $tenant->getKey()]);
    config(['app.domain' => 'odessa-pos-production.up.railway.app']);

    $this->actingAs($user)->get('https://odessa-pos-production.up.railway.app/t/'.$otherTenant->slug)->assertForbidden();
});
