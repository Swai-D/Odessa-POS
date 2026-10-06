<?php

use App\Support\TenantSettings;

it('saves business settings and feature flags for the current tenant only', function (): void {
    $a = createTenant('shop-a');
    $b = createTenant('shop-b');
    $user = createTenantUser($a, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', [
        'business_name' => 'Mama Asha Shop',
        'currency' => 'KES',
        'receipt_footer' => 'Asante, karibu tena',
        'features' => ['barcode_scanner' => '1', 'cash_drawer' => '0'],
    ])->assertRedirect('/settings')->assertSessionHasNoErrors();

    $settings = new TenantSettings($a->fresh());
    expect($settings->get('business_name'))->toBe('Mama Asha Shop')
        ->and($settings->get('currency'))->toBe('KES')
        ->and($settings->feature('barcode_scanner'))->toBeTrue()
        ->and($settings->feature('cash_drawer'))->toBeFalse()
        ->and($settings->feature('batch_tracking'))->toBeFalse()
        ->and((new TenantSettings($b->fresh()))->get('business_name'))->toBeNull();
});

it('keeps unrelated settings keys when saving', function (): void {
    $tenant = createTenant('shop-a');
    $tenant->update(['settings' => ['locale' => 'sw', 'features' => ['legacy_flag' => true]]]);
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', [
        'business_name' => 'Shop', 'currency' => 'TZS',
    ])->assertSessionHasNoErrors();

    $settings = new TenantSettings($tenant->fresh());
    expect($settings->get('locale'))->toBe('sw')->and($settings->get('features.legacy_flag'))->toBeTrue();
});

it('rejects unknown currencies and a missing business name', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', ['business_name' => '', 'currency' => 'XXX'])
        ->assertSessionHasErrors(['business_name', 'currency']);
});

it('forbids settings without the permission', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings')->assertForbidden();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', ['business_name' => 'X', 'currency' => 'TZS'])->assertForbidden();
});

it('shows the saved values on the settings page', function (): void {
    $tenant = createTenant('shop-a');
    $tenant->update(['settings' => ['receipt_footer' => 'Bidhaa hazirudi']]);
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings')->assertOk()->assertSee('Bidhaa hazirudi', false);
});
