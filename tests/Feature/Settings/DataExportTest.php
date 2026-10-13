<?php

use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Services\DataExport;
use App\Support\Tenancy\TenantContext;

function exportShop(string $slug): array
{
    $tenant = createTenant($slug);
    $owner = createTenantUser($tenant, [...posPermissions(), 'settings.manage']);
    $fixture = posFixture($tenant);

    return [$tenant, $owner, $fixture];
}

function exportCsv(object $test, $user, string $slug, string $dataset): string
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->get('/settings/export/'.$dataset)->assertOk()->streamedContent();
}

it('lists every dataset on the export page and links to it from settings', function () {
    [, $owner] = exportShop('exp-a');

    $page = $this->actingAs($owner)->withHeader('X-Tenant', 'exp-a')->get('/settings/export')->assertOk();
    foreach (app(DataExport::class)->keys() as $key) {
        $page->assertSee('data-dataset="'.$key.'"', false);
    }

    $this->actingAs($owner)->withHeader('X-Tenant', 'exp-a')->get('/settings')->assertSee('data-export-link', false);
});

it('downloads products with money in major units', function () {
    [, $owner] = exportShop('exp-b');

    $csv = exportCsv($this, $owner, 'exp-b', 'products');

    expect($csv)->toContain('sku,barcode,name,type')
        ->and($csv)->toContain('SOAP')
        ->and($csv)->toContain('1000.00')
        ->and($csv)->toContain('500.00');
});

it('downloads sales, items and payments', function () {
    [, $owner, $fixture] = exportShop('exp-c');
    [$product, $warehouse, $customer] = $fixture;
    checkout($this, 'exp-c', $owner, cart($product, $warehouse, ['customer_id' => $customer->id, 'payments' => [['method' => 'cash', 'amount' => 50000]]]))->assertCreated();

    expect(exportCsv($this, $owner, 'exp-c', 'sales'))->toContain('Asha')->toContain('2000.00')->toContain('1500.00')
        ->and(exportCsv($this, $owner, 'exp-c', 'sale_items'))->toContain('Soap')
        ->and(exportCsv($this, $owner, 'exp-c', 'payments'))->toContain('cash')->toContain('500.00')
        ->and(exportCsv($this, $owner, 'exp-c', 'customers'))->toContain('Asha')->toContain('1500.00')
        ->and(exportCsv($this, $owner, 'exp-c', 'stock'))->toContain('Main');
});

it('never includes another shop\'s data', function () {
    [, $ownerA] = exportShop('exp-d');
    exportShop('exp-e');

    // Both shops have a product with SKU SOAP; each export must hold only its own one.
    expect(substr_count(exportCsv($this, $ownerA, 'exp-d', 'products'), 'SOAP'))->toBe(1);
});

it('refuses unknown datasets and people without settings access', function () {
    [$tenant, $owner] = exportShop('exp-f');
    $cashier = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'exp-f')->get('/settings/export/passwords')->assertNotFound();
    $this->actingAs($cashier)->withHeader('X-Tenant', 'exp-f')->get('/settings/export')->assertForbidden();
    $this->actingAs($cashier)->withHeader('X-Tenant', 'exp-f')->get('/settings/export/products')->assertForbidden();
});

it('records each download in the audit log', function () {
    [$tenant, $owner] = exportShop('exp-g');

    exportCsv($this, $owner, 'exp-g', 'customers');

    $log = app(TenantContext::class)->run($tenant, fn () => AuditLog::query()->where('event', 'data_exported')->first());
    expect($log)->not->toBeNull()->and($log->subject_type)->toBe('Export')->and($log->subject_label)->toBe('customers');
});
