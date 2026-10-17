<?php

use App\Domain\Integrations\Services\EscPosReceipt;

function scannerShop(): array
{
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, array_merge(posPermissions(), ['settings.manage']));

    return [$tenant, $user];
}

function setFeature($test, $user, string $feature, bool $on): void
{
    $test->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', [
        'business_name' => 'Shop', 'currency' => 'TZS', 'features' => [$feature => $on ? '1' : '0'],
    ])->assertSessionHasNoErrors();
}

function tillPage($test, $user)
{
    return $test->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/pos')->assertOk();
}

it('keeps scanner mode and the cash drawer off at the till by default', function (): void {
    [, $user] = scannerShop();

    tillPage($this, $user)->assertSee('scanner: false', false)->assertDontSee('pos-open-drawer', false)->assertDontSee('js/escpos-print.js', false);
});

it('turns scanner mode on at the till when the switch is on', function (): void {
    [, $user] = scannerShop();
    setFeature($this, $user, 'barcode_scanner', true);

    tillPage($this, $user)->assertSee('scanner: true', false);

    setFeature($this, $user, 'barcode_scanner', false);
    tillPage($this, $user)->assertSee('scanner: false', false);
});

it('does nothing for the cash drawer until an ESC/POS printer is set up, and says so in settings', function (): void {
    [, $user] = scannerShop();
    setFeature($this, $user, 'cash_drawer', true);

    tillPage($this, $user)->assertDontSee('pos-open-drawer', false);
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings')->assertOk()->assertSee(__('settings.drawer_needs_printer'));
});

it('gives the till a drawer button and the pulse bytes once the switch and an ESC/POS printer are on', function (): void {
    [, $user] = scannerShop();
    setFeature($this, $user, 'cash_drawer', true);
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings/integrations/printer', [
        'enabled' => '1', 'driver' => 'escpos',
        'config' => ['connection' => 'usb', 'paper_width' => '80', 'copies' => '1', 'cut' => '1'],
    ])->assertSessionHasNoErrors();

    tillPage($this, $user)->assertSee('pos-open-drawer', false)->assertSee('js/escpos-print.js', false)
        ->assertSee(base64_encode(EscPosReceipt::DRAWER_PULSE), false)->assertSee('"connection":"usb"', false);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings')->assertDontSee(__('settings.drawer_needs_printer'));
});

it('uses the standard ESC/POS drawer pulse command', function (): void {
    expect(bin2hex(EscPosReceipt::DRAWER_PULSE))->toBe('1b700019fa');
});
