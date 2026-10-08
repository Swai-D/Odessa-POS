<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Services\ReportService;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

function reportsFor($tenant, ?Carbon $from = null, ?Carbon $to = null, ?Closure $callback = null): mixed
{
    return app(TenantContext::class)->run($tenant, function () use ($from, $to, $callback) {
        $service = new ReportService($from ?? Carbon::today()->subDays(6), $to ?? Carbon::today());

        return $callback($service);
    });
}

it('reports sales, best sellers, payments, balances and stock from the shop\'s own records', function (): void {
    $tenant = createTenant('shop-r');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse, $customer] = posFixture($tenant);

    checkout($this, 'shop-r', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertCreated();
    checkout($this, 'shop-r', $user, cart($product, $warehouse, [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payments' => [],
    ]))->assertCreated();

    // Another shop's sale must not leak in.
    $other = createTenant('shop-s');
    $otherUser = createTenantUser($other, posPermissions());
    [$otherProduct, $otherWarehouse] = posFixture($other);
    checkout($this, 'shop-s', $otherUser, cart($otherProduct, $otherWarehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertCreated();

    $result = reportsFor($tenant, callback: fn (ReportService $r) => [
        'summary' => $r->salesSummary(),
        'top' => $r->topProducts()[0],
        'methods' => $r->paymentsByMethod(),
        'balances' => $r->customerBalances(),
        'stock' => $r->stockValuation(),
        'days' => $r->salesByDay(),
    ]);

    expect($result['summary'])->toBe(['count' => 2, 'gross' => 300000, 'discounts' => 0, 'tax' => 0, 'returns' => 0, 'net' => 300000, 'paid' => 200000, 'credit' => 100000])
        ->and($result['top']['name'])->toBe('Soap')
        ->and($result['top']['quantity'])->toBe(3.0)
        ->and($result['top']['revenue'])->toBe(300000)
        ->and($result['top']['profit'])->toBe(150000)
        ->and($result['methods'])->toHaveCount(1)
        ->and($result['methods'][0]['method'])->toBe('cash')
        ->and($result['methods'][0]['amount'])->toBe(200000)
        ->and($result['balances'])->toHaveCount(1)
        ->and($result['balances'][0]['name'])->toBe('Asha')
        ->and($result['balances'][0]['balance'])->toBe(100000)
        ->and($result['stock'])->toBe(['value' => 350000, 'units' => 7.0, 'products' => 1])
        ->and($result['days'])->toHaveCount(7)
        ->and(end($result['days'])['total'])->toBe(300000);
});

it('leaves sales outside the period out', function (): void {
    $tenant = createTenant('shop-p');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    checkout($this, 'shop-p', $user, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();

    $past = reportsFor($tenant, Carbon::today()->subDays(30), Carbon::today()->subDays(10), fn (ReportService $r) => $r->salesSummary());

    expect($past['count'])->toBe(0)->and($past['gross'])->toBe(0);
});

it('excludes tax from revenue and profit', function (): void {
    $tenant = createTenant('shop-t');
    $user = createTenantUser($tenant, posPermissions());
    [, $warehouse] = posFixture($tenant);
    $vat = app(TenantContext::class)->run($tenant, function () use ($warehouse): Product {
        $product = Product::create([
            'name' => 'Taxed', 'sku' => 'TAX', 'type' => 'standard', 'cost_price' => 50000,
            'selling_price' => 118000, 'tax_rate' => 18, 'tax_inclusive' => true, 'track_stock' => true,
        ]);
        app(StockService::class)->move($product, $warehouse, 5, StockMovement::OPENING);

        return $product;
    });

    checkout($this, 'shop-t', $user, [
        'idempotency_key' => 'tax-key-1',
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $vat->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 118000]],
    ])->assertCreated();

    $top = reportsFor($tenant, callback: fn (ReportService $r) => collect($r->topProducts())->firstWhere('name', 'Taxed'));

    expect($top['revenue'])->toBe(100000)->and($top['profit'])->toBe(50000);
});

it('shows the reports page only to users with the permission', function (): void {
    $tenant = createTenant('shop-v');
    $viewer = createTenantUser($tenant, ['reports.view', 'dashboard.view']);
    $other = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-v')->get('/reports')->assertOk()->assertSee(__('reports.net_sales'));
    $this->actingAs($other)->withHeader('X-Tenant', 'shop-v')->get('/reports')->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-v')->get('/reports?from=2026-10-05&to=2026-10-01')->assertSessionHasErrors('to');
});

it('exports a report as CSV with Excel-safe text', function (): void {
    $tenant = createTenant('shop-x');
    $user = createTenantUser($tenant, [...posPermissions(), 'reports.view']);
    [$product, $warehouse] = posFixture($tenant);
    app(TenantContext::class)->run($tenant, fn () => $product->update(['name' => '=HYPERLINK("x")']));
    checkout($this, 'shop-x', $user, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();

    $response = $this->actingAs($user)->withHeader('X-Tenant', 'shop-x')->get('/reports/export/products');
    $csv = $response->streamedContent();

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain(__('reports.revenue'))
        ->toContain("'=HYPERLINK")
        ->toContain('2000.00')
        ->toContain('1000.00');
});

it('guards report exports by plan, permission and report name', function (): void {
    $basic = createTenant('shop-xb', 'basic');
    $basicUser = createTenantUser($basic, ['reports.view']);
    $this->actingAs($basicUser)->withHeader('X-Tenant', 'shop-xb')->get('/reports/export/daily')->assertForbidden();

    $tenant = createTenant('shop-xe');
    $none = createTenantUser($tenant, ['dashboard.view']);
    $viewer = createTenantUser($tenant, ['reports.view']);
    $this->actingAs($none)->withHeader('X-Tenant', 'shop-xe')->get('/reports/export/daily')->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-xe')->get('/reports/export/secrets')->assertNotFound();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-xe')->get('/reports/export/daily')->assertOk();
});
