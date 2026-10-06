<?php

use App\Domain\Integrations\Contracts\Driver;
use App\Domain\Integrations\Models\TenantIntegration;
use App\Domain\Integrations\Services\IntegrationManager;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class SecretTestDriver implements Driver
{
    public static function key(): string
    {
        return 'secret-test';
    }

    public static function label(): string
    {
        return 'Secret test';
    }

    public static function fields(): array
    {
        return [
            ['name' => 'shortcode', 'label' => 'Shortcode', 'type' => 'text', 'required' => true],
            ['name' => 'api_key', 'label' => 'API key', 'type' => 'password', 'required' => true, 'secret' => true],
        ];
    }
}

function integrationUser(string $slug = 'shop-a', array $permissions = ['settings.manage']): array
{
    $tenant = createTenant($slug);

    return [$tenant, createTenantUser($tenant, $permissions)];
}

function saveIntegration($test, string $slug, $user, string $channel, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->put("/settings/integrations/{$channel}", $payload);
}

function activeIntegration($tenant, string $channel): ?array
{
    return app(TenantContext::class)->run($tenant, fn () => app(IntegrationManager::class)->active($channel));
}

it('saves a printer driver with its settings and switches the channel on', function (): void {
    [$tenant, $user] = integrationUser();

    saveIntegration($this, 'shop-a', $user, 'printer', [
        'enabled' => '1', 'driver' => 'escpos',
        'config' => ['connection' => 'usb', 'paper_width' => '58', 'copies' => '2', 'cut' => '0'],
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/integrations');

    $active = activeIntegration($tenant->fresh(), 'printer');
    expect($active['driver'])->toBe('escpos')
        ->and($active['settings'])->toMatchArray(['connection' => 'usb', 'paper_width' => '58', 'copies' => '2', 'cut' => '0']);
});

it('stays inactive when switched off, and keeps the saved configuration', function (): void {
    [$tenant, $user] = integrationUser();
    $config = ['connection' => 'serial', 'paper_width' => '80', 'copies' => '1', 'cut' => '1'];

    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '1', 'driver' => 'escpos', 'config' => $config]);
    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '0', 'driver' => 'escpos', 'config' => $config]);

    expect(activeIntegration($tenant->fresh(), 'printer'))->toBeNull();
    app(TenantContext::class)->run($tenant->fresh(), fn () => expect(TenantIntegration::query()->count())->toBe(1));
});

it('requires a driver when enabling and rejects invalid options', function (): void {
    [, $user] = integrationUser();

    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '1'])->assertSessionHasErrors('driver');
    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '1', 'driver' => 'nope'])->assertSessionHasErrors('driver');
    saveIntegration($this, 'shop-a', $user, 'printer', [
        'enabled' => '1', 'driver' => 'escpos', 'config' => ['connection' => 'wifi', 'paper_width' => '99', 'copies' => '0', 'cut' => '1'],
    ])->assertSessionHasErrors(['config.connection', 'config.paper_width', 'config.copies']);
    saveIntegration($this, 'shop-a', $user, 'unknown', ['enabled' => '0'])->assertNotFound();
});

it('stores secrets encrypted, never shows them again and keeps them when left blank', function (): void {
    config(['integrations.channels.payments.drivers' => [SecretTestDriver::class]]);
    [$tenant, $user] = integrationUser();

    saveIntegration($this, 'shop-a', $user, 'payments', [
        'enabled' => '1', 'driver' => 'secret-test', 'config' => ['shortcode' => '1234', 'api_key' => 'super-secret-key'],
    ])->assertSessionHasNoErrors();

    expect(DB::table('tenant_integrations')->value('secrets'))->not->toContain('super-secret-key')
        ->and(activeIntegration($tenant->fresh(), 'payments')['secrets'])->toBe(['api_key' => 'super-secret-key']);

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings/integrations')->assertOk();
    expect($page->getContent())->not->toContain('super-secret-key');

    // Change the shortcode and leave the key blank: the key is kept.
    saveIntegration($this, 'shop-a', $user, 'payments', [
        'enabled' => '1', 'driver' => 'secret-test', 'config' => ['shortcode' => '9999', 'api_key' => ''],
    ])->assertSessionHasNoErrors();

    $active = activeIntegration($tenant->fresh(), 'payments');
    expect($active['settings']['shortcode'])->toBe('9999')->and($active['secrets']['api_key'])->toBe('super-secret-key');
});

it('requires a secret the first time', function (): void {
    config(['integrations.channels.payments.drivers' => [SecretTestDriver::class]]);
    [, $user] = integrationUser();

    saveIntegration($this, 'shop-a', $user, 'payments', [
        'enabled' => '1', 'driver' => 'secret-test', 'config' => ['shortcode' => '1234', 'api_key' => ''],
    ])->assertSessionHasErrors('config.api_key');
});

it('keeps integrations separate per tenant and behind settings.manage', function (): void {
    [$a, $userA] = integrationUser('shop-a');
    [$b] = integrationUser('shop-b');
    $viewer = createTenantUser($a, ['sales.view']);

    saveIntegration($this, 'shop-a', $userA, 'printer', [
        'enabled' => '1', 'driver' => 'browser',
    ])->assertSessionHasNoErrors();

    expect(activeIntegration($a->fresh(), 'printer')['driver'])->toBe('browser')
        ->and(activeIntegration($b->fresh(), 'printer'))->toBeNull();

    saveIntegration($this, 'shop-a', $viewer, 'printer', ['enabled' => '0'])->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-a')->get('/settings/integrations')->assertForbidden();
});

it('does not let the general settings form touch channel switches', function (): void {
    [$tenant, $user] = integrationUser();
    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '1', 'driver' => 'browser']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/settings', ['business_name' => 'Shop', 'currency' => 'TZS'])
        ->assertSessionHasNoErrors();

    expect(activeIntegration($tenant->fresh(), 'printer')['driver'])->toBe('browser');
});

it('serves ESC/POS bytes only when the thermal printer is active', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, array_merge(posPermissions(), ['settings.manage']));
    [$product, $warehouse] = posFixture($tenant);
    $saleId = checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->json('id');

    $get = fn () => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->getJson("/sales/{$saleId}/escpos");

    $get()->assertNotFound();

    saveIntegration($this, 'shop-a', $user, 'printer', [
        'enabled' => '1', 'driver' => 'escpos',
        'config' => ['connection' => 'serial', 'paper_width' => '58', 'copies' => '2', 'cut' => '1'],
    ])->assertSessionHasNoErrors();

    $job = $get()->assertOk()->assertJson(['connection' => 'serial', 'copies' => 2])->json();
    $bytes = base64_decode($job['data'], true);

    expect($bytes)->toStartWith("\x1B@")
        ->and($bytes)->toContain('SL-000001')
        ->and($bytes)->toContain('Soap')
        ->and($bytes)->toContain("\x1DV");

    foreach (explode("\n", $bytes) as $line) {
        // 58 mm paper holds 32 characters; control codes are not printed.
        expect(strlen(preg_replace('/[\x00-\x1F]|[\x1B\x1D].?/', '', $line) ?? ''))->toBeLessThanOrEqual(40);
    }

    // The browser-print driver does not offer ESC/POS.
    saveIntegration($this, 'shop-a', $user, 'printer', ['enabled' => '1', 'driver' => 'browser']);
    $get()->assertNotFound();
});
