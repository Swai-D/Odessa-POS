<?php

it('shows a clean sign-in page with no dead links or template branding', function (): void {
    $html = $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain('Odessa POS')->toContain('data-forgot-hint')
        ->and($html)->not->toContain('forgot-password.html')
        ->and($html)->not->toContain('register.html')
        ->and($html)->not->toContain('facebook-logo')
        ->and($html)->not->toContain('Dreams')
        ->and($html)->not->toContain('cdn-cgi');
});

it('names the shop on its own address', function (): void {
    createTenant('login-shop');

    $this->withHeader('X-Tenant', 'login-shop')->get('/login')->assertOk()->assertSee('Login-shop');
});

it('shows why a sign-in failed', function (): void {
    $tenant = createTenant('login-err');
    loginUser($tenant->getKey(), 'owner@err.test');

    $this->from('/login')->post('/login', ['email' => 'owner@err.test', 'password' => 'wrong-password'])->assertRedirect('/login');

    $this->get('/login')->assertSee(__('auth.failed'));
});

it('lets a visitor switch the sign-in page to Swahili', function (): void {
    $this->post('/language/sw')->assertRedirect();

    $this->get('/login')->assertOk()->assertSee('Ingia')->assertSee('Nikumbuke');
});

it('lets the visitor\'s choice win over the shop default', function (): void {
    $tenant = createTenant('login-lang');
    $tenant->update(['settings' => ['locale' => 'en']]);

    $this->withHeader('X-Tenant', 'login-lang')->post('/language/sw')->assertRedirect();

    $this->withHeader('X-Tenant', 'login-lang')->get('/login')->assertSee('Nikumbuke');
});

it('has no reset-by-email routes, since the Owner resets passwords', function (): void {
    $this->get('/forgot-password')->assertNotFound();
});

it('shows Odessa branding in the app footer', function (): void {
    $tenant = createTenant('login-foot');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'login-foot')->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('Odessa Lab Technologies')->not->toContain('DreamsPOS');
});
