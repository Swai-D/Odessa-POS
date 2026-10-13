<?php

it('shows the terms and privacy pages without signing in', function (string $path, string $title, string $text) {
    $this->get($path)->assertOk()->assertSee($title)->assertSee($text)->assertSee('Last updated: 2026-10-12');
})->with([
    'terms' => ['/terms', 'Terms of Service', 'We never delete your data because a payment is late'],
    'privacy' => ['/privacy', 'Privacy Policy', 'We do not sell personal data'],
]);

it('shows the pages in Swahili', function () {
    $this->withSession(['locale' => 'sw'])->get('/terms')->assertOk()->assertSee('Masharti ya Huduma');
    $this->withSession(['locale' => 'sw'])->get('/privacy')->assertOk()->assertSee('Sera ya Faragha');
});

it('links to them from the sign-in page', function () {
    $this->get('/login')->assertOk()->assertSee(route('legal.terms'), false)->assertSee(route('legal.privacy'), false);
});

it('links to them from inside the app', function () {
    $tenant = createTenant('legal-shop');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'legal-shop')->get('/dashboard')->assertSee(route('legal.terms'), false);
});

it('has the same sections in both languages', function () {
    foreach (['terms', 'privacy'] as $page) {
        $en = require base_path('lang/en/legal.php');
        $sw = require base_path('lang/sw/legal.php');

        expect(count($sw[$page]['sections']))->toBe(count($en[$page]['sections']));
    }
});
