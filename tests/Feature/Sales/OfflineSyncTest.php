<?php

use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Sales\Models\Sale;
use App\Support\Tenancy\TenantContext;

/** The block the till adds when it syncs a sale it made offline. */
function offlineBlock(int $expectedTotal, ?string $soldAt = null): array
{
    return ['sold_at' => $soldAt ?? now()->subHours(3)->toIso8601String(), 'expected_total' => $expectedTotal];
}

it('records an offline sale even when stock is short, flags it and keeps the device time', function () {
    $tenant = createTenant('off-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 1);
    $soldAt = now()->subHours(3)->startOfSecond();

    checkout($this, 'off-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
        'offline' => offlineBlock(200000, $soldAt->toIso8601String()),
    ]))->assertCreated()->assertJson(['needs_review' => true, 'total' => 200000]);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse, $soldAt) {
        $sale = Sale::query()->sole();

        expect(app(StockService::class)->quantity($product, $warehouse))->toBe(-1.0)
            ->and($sale->review_reason)->toBe('stock')
            ->and($sale->offline_synced_at)->not->toBeNull()
            ->and($sale->sold_at->equalTo($soldAt))->toBeTrue()
            ->and($sale->payments->first()->paid_at->equalTo($soldAt))->toBeTrue();
    });
});

it('still refuses an online sale that the stock cannot cover', function () {
    $tenant = createTenant('off-b');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 1);

    checkout($this, 'off-b', $user, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertUnprocessable();
});

it('does not flag an offline sale that matches stock and price', function () {
    $tenant = createTenant('off-c');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'off-c', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
        'offline' => offlineBlock(200000),
    ]))->assertCreated()->assertJson(['needs_review' => false]);

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->sole()->review_reason))->toBeNull();
});

it('flags a price change and keeps the server price', function () {
    $tenant = createTenant('off-d');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'off-d', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 150000]],
        'offline' => offlineBlock(150000),
    ]))->assertCreated()->assertJson(['total' => 200000, 'balance_due' => 50000, 'needs_review' => true]);

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->sole()->review_reason))->toContain('price')->toContain('credit');
});

it('trims a non-cash payment that is now above the total instead of refusing the sale', function () {
    $tenant = createTenant('off-e');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'off-e', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'mobile_money', 'amount' => 300000, 'reference' => 'MP1']],
        'offline' => offlineBlock(300000),
    ]))->assertCreated()->assertJson(['total' => 200000, 'amount_paid' => 200000, 'balance_due' => 0]);

    app(TenantContext::class)->run($tenant, function () {
        $sale = Sale::query()->with('payments')->sole();

        expect($sale->review_reason)->toContain('payment')->and($sale->payments->sole()->amount)->toBe(200000);
    });
});

it('syncing the same offline sale twice records it once', function () {
    $tenant = createTenant('off-f');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $payload = cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]], 'offline' => offlineBlock(200000)]);

    checkout($this, 'off-f', $user, $payload)->assertCreated();
    checkout($this, 'off-f', $user, $payload)->assertOk();

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse) {
        expect(Sale::query()->count())->toBe(1)->and(app(StockService::class)->quantity($product, $warehouse))->toBe(8.0);
    });
});

it('refuses an offline sale from the future or older than the limit', function () {
    $tenant = createTenant('off-g');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $pay = ['payments' => [['method' => 'cash', 'amount' => 200000]]];

    checkout($this, 'off-g', $user, cart($product, $warehouse, $pay + ['offline' => offlineBlock(200000, now()->addDay()->toIso8601String())]))->assertUnprocessable();
    checkout($this, 'off-g', $user, cart($product, $warehouse, $pay + ['offline' => offlineBlock(200000, now()->subDays(40)->toIso8601String())]))->assertUnprocessable();
});

it('lets new stock cover the deficit first so the cost batches never exceed the stock', function () {
    $tenant = createTenant('off-h');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 1);

    checkout($this, 'off-h', $user, cart($product, $warehouse, [
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
        'payments' => [['method' => 'cash', 'amount' => 300000]],
        'offline' => offlineBlock(300000),
    ]))->assertCreated();

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse) {
        app(StockService::class)->move($product, $warehouse, 10, StockMovement::PURCHASE, 'PO-1', null, null, 50000);

        expect(app(StockService::class)->quantity($product, $warehouse))->toBe(8.0)
            ->and((float) StockBatch::query()->sum('quantity_remaining'))->toBe(8.0);
    });
});

it('gives the till its products with stock and its customers for offline use', function () {
    $tenant = createTenant('off-i');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 7);

    $response = $this->actingAs($user)->withHeader('X-Tenant', 'off-i')->getJson('/pos/snapshot?warehouse='.$warehouse->id)->assertOk();

    expect($response->json('products.0.sku'))->toBe('SOAP')
        ->and($response->json('products.0.stock'))->toBe(7)
        ->and($response->json('products.0.price'))->toBe(100000)
        ->and($response->json('customers.0.name'))->toBe('Asha')
        ->and($response->json('generated_at'))->not->toBeNull();

    $this->actingAs(createTenantUser($tenant, ['dashboard.view']))->withHeader('X-Tenant', 'off-i')->getJson('/pos/snapshot?warehouse='.$warehouse->id)->assertForbidden();
});

it('hands out a fresh CSRF token to a till that wants to sync', function () {
    $tenant = createTenant('off-j');
    $user = createTenantUser($tenant, posPermissions());

    expect($this->actingAs($user)->withHeader('X-Tenant', 'off-j')->getJson('/pos/session')->assertOk()->json('csrf'))->toBeString()->not->toBeEmpty();
});

it('lists flagged offline sales and lets a manager mark them as checked', function () {
    $tenant = createTenant('off-k');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 1);

    checkout($this, 'off-k', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
        'offline' => offlineBlock(200000),
    ]))->assertCreated();
    $sale = app(TenantContext::class)->run($tenant, fn () => Sale::query()->sole());

    $this->actingAs($user)->withHeader('X-Tenant', 'off-k')->get('/sales?status=review')->assertOk()->assertSee($sale->number);
    $this->actingAs($user)->withHeader('X-Tenant', 'off-k')->get('/sales/'.$sale->id)->assertOk()->assertSee(__('pos.sales.review.mark'));

    $this->actingAs($user)->withHeader('X-Tenant', 'off-k')->post('/sales/'.$sale->id.'/review')->assertRedirect();

    app(TenantContext::class)->run($tenant, function () use ($user) {
        $fresh = Sale::query()->sole();

        expect($fresh->needsReview())->toBeFalse()->and($fresh->reviewed_by)->toBe($user->id);
    });
    $this->actingAs($user)->withHeader('X-Tenant', 'off-k')->get('/sales?status=review')->assertOk()->assertDontSee($sale->number);
});
