<?php

use App\Domain\Inventory\Services\StockService;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use App\Support\Tenancy\TenantContext;

/** Sells $quantity of the fixture product (price 1,000.00) and returns the sale id. */
function soldSale($test, string $slug, $user, array $fixture, int $quantity = 4, array $extra = []): int
{
    [$product, $warehouse] = $fixture;

    return checkout($test, $slug, $user, cart($product, $warehouse, array_merge([
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        'payments' => [['method' => 'cash', 'amount' => $quantity * 100000]],
    ], $extra)))->assertCreated()->json('id');
}

function itemId(object $tenant, int $saleId): int
{
    return app(TenantContext::class)->run($tenant, fn () => SaleItem::query()->where('sale_id', $saleId)->firstOrFail()->id);
}

function postReturn($test, string $slug, $user, int $saleId, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post("/sales/{$saleId}/returns", $payload);
}

it('returns part of a paid sale: restocks and records the refund', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);
    $saleId = soldSale($this, 'shop-a', $user, $fixture);
    $item = itemId($tenant, $saleId);

    postReturn($this, 'shop-a', $user, $saleId, [
        'items' => [['sale_item_id' => $item, 'quantity' => 1]],
        'refund_method' => 'cash',
        'reason' => 'damaged',
    ])->assertSessionHasNoErrors()->assertRedirect("/sales/{$saleId}");

    app(TenantContext::class)->run($tenant, function () use ($fixture, $saleId): void {
        $return = SaleReturn::query()->firstOrFail();
        expect($return->number)->toBe('RT-000001')
            ->and($return->total)->toBe(100000)
            ->and($return->refunded)->toBe(100000)
            ->and($return->credit_applied)->toBe(0)
            ->and(Sale::query()->findOrFail($saleId)->returned_total)->toBe(100000)
            // 10 in stock, sold 4, one returned.
            ->and(app(StockService::class)->quantity($fixture[0], $fixture[1]))->toBe(7.0);
    });
});

it('refuses to return more than was sold, across several returns', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);
    $saleId = soldSale($this, 'shop-a', $user, $fixture, 2);
    $item = itemId($tenant, $saleId);

    postReturn($this, 'shop-a', $user, $saleId, ['items' => [['sale_item_id' => $item, 'quantity' => 2]], 'refund_method' => 'cash'])
        ->assertSessionHasNoErrors();
    postReturn($this, 'shop-a', $user, $saleId, ['items' => [['sale_item_id' => $item, 'quantity' => 1]], 'refund_method' => 'cash'])
        ->assertSessionHasErrors('items');

    app(TenantContext::class)->run($tenant, function () use ($fixture): void {
        expect(SaleReturn::query()->count())->toBe(1)
            ->and(app(StockService::class)->quantity($fixture[0], $fixture[1]))->toBe(10.0);
    });
});

it('reduces the customer balance before paying anything out', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);

    // 1 unit on credit: 1,000.00 owed, nothing paid.
    $saleId = checkout($this, 'shop-a', $user, cart($fixture[0], $fixture[1], [
        'customer_id' => $fixture[2]->id,
        'items' => [['product_id' => $fixture[0]->id, 'quantity' => 1]],
    ]))->assertCreated()->json('id');

    // Returning it needs no refund method: it only cancels the debt.
    postReturn($this, 'shop-a', $user, $saleId, ['items' => [['sale_item_id' => itemId($tenant, $saleId), 'quantity' => 1]]])
        ->assertSessionHasNoErrors();

    app(TenantContext::class)->run($tenant, function () use ($saleId): void {
        $sale = Sale::query()->findOrFail($saleId);
        $return = SaleReturn::query()->firstOrFail();

        expect($sale->balance_due)->toBe(0)->and($sale->payment_status)->toBe('paid')
            ->and($return->credit_applied)->toBe(100000)->and($return->refunded)->toBe(0)
            ->and($return->refund_method)->toBeNull();
    });
});

it('requires a refund method when money has to be paid out', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);
    $saleId = soldSale($this, 'shop-a', $user, $fixture);

    postReturn($this, 'shop-a', $user, $saleId, ['items' => [['sale_item_id' => itemId($tenant, $saleId), 'quantity' => 1]]])
        ->assertSessionHasErrors('refund_method');

    app(TenantContext::class)->run($tenant, fn () => expect(SaleReturn::query()->count())->toBe(0));
});

it('refunds the exact remainder when the last units come back, so no cents are lost', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);
    // 3 units with a 10.00 fixed discount: line total 2,990.00 does not split evenly three ways.
    $saleId = soldSale($this, 'shop-a', $user, $fixture, 3, [
        'discount' => ['type' => 'fixed', 'value' => 1000],
        'payments' => [['method' => 'cash', 'amount' => 300000]],
    ]);
    $item = itemId($tenant, $saleId);

    foreach ([1, 1, 1] as $unused) {
        postReturn($this, 'shop-a', $user, $saleId, ['items' => [['sale_item_id' => $item, 'quantity' => 1]], 'refund_method' => 'cash'])
            ->assertSessionHasNoErrors();
    }

    app(TenantContext::class)->run($tenant, function () use ($saleId): void {
        $sale = Sale::query()->findOrFail($saleId);
        expect($sale->returned_total)->toBe($sale->total)
            ->and(SaleReturn::query()->sum('total'))->toBe($sale->total);
    });
});

it('cannot return items of another tenant\'s sale and needs sales.manage', function (): void {
    $a = createTenant('shop-a');
    $b = createTenant('shop-b');
    $userA = createTenantUser($a, posPermissions());
    $userB = createTenantUser($b, posPermissions());
    $viewerA = createTenantUser($a, ['sales.view']);
    $fixture = posFixture($a);
    $saleId = soldSale($this, 'shop-a', $userA, $fixture);
    $item = itemId($a, $saleId);
    $payload = ['items' => [['sale_item_id' => $item, 'quantity' => 1]], 'refund_method' => 'cash'];

    postReturn($this, 'shop-b', $userB, $saleId, $payload)->assertNotFound();
    postReturn($this, 'shop-a', $viewerA, $saleId, $payload)->assertForbidden();

    app(TenantContext::class)->run($a, fn () => expect(SaleReturn::query()->count())->toBe(0));
});

it('shows the return on the sale page', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    $fixture = posFixture($tenant);
    $saleId = soldSale($this, 'shop-a', $user, $fixture);

    postReturn($this, 'shop-a', $user, $saleId, [
        'items' => [['sale_item_id' => itemId($tenant, $saleId), 'quantity' => 1]],
        'refund_method' => 'cash',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get("/sales/{$saleId}")
        ->assertOk()->assertSee('RT-000001');
});
