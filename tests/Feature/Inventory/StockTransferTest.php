<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Support\Tenancy\TenantContext;

/** @return array{0: \App\Models\Tenant, 1: \App\Models\User, 2: Warehouse, 3: Warehouse, 4: Product} */
function transferShop(string $slug, array $permissions = ['inventory.view', 'inventory.manage'], float $onHand = 10, array $productOverrides = []): array
{
    $tenant = createTenant($slug, 'medium');
    $user = createTenantUser($tenant, $permissions);

    [$main, $branch, $product] = app(TenantContext::class)->run($tenant, function () use ($onHand, $productOverrides): array {
        $main = Warehouse::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]);
        $branch = Warehouse::create(['name' => 'Branch', 'code' => 'BR', 'is_default' => false, 'is_active' => true]);
        $product = Product::create($productOverrides + [
            'name' => 'Soap', 'sku' => 'SOAP-1', 'type' => 'standard', 'cost_price' => 100, 'selling_price' => 200,
            'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true,
        ]);
        if ($onHand > 0) {
            app(StockService::class)->move($product, $main, $onHand, StockMovement::OPENING, 'start');
        }

        return [$main, $branch, $product];
    });

    return [$tenant, $user, $main, $branch, $product];
}

function transferPayload(Warehouse $from, Warehouse $to, Product $product, string|float $qty, array $overrides = []): array
{
    return $overrides + [
        'idempotency_key' => 'tr-'.bin2hex(random_bytes(6)),
        'from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id,
        'items' => [['product_id' => $product->id, 'quantity' => $qty]],
    ];
}

function stockAt($tenant, Product $product, Warehouse $warehouse): float
{
    return app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->quantity($product, $warehouse));
}

it('moves stock out of one warehouse and into the other, with a ledger row on each side', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t1');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t1')->post('/stock-transfers', transferPayload($main, $branch, $product, 4, ['note' => 'weekly top-up']))
        ->assertRedirect();

    expect(stockAt($tenant, $product, $main))->toBe(6.0)->and(stockAt($tenant, $product, $branch))->toBe(4.0);

    app(TenantContext::class)->run($tenant, function () use ($main, $branch): void {
        $transfer = StockTransfer::query()->with('items')->firstOrFail();
        expect($transfer->number)->toStartWith('TR-')->and($transfer->note)->toBe('weekly top-up')->and($transfer->items)->toHaveCount(1);

        $out = StockMovement::query()->where('type', StockMovement::TRANSFER_OUT)->firstOrFail();
        $in = StockMovement::query()->where('type', StockMovement::TRANSFER_IN)->firstOrFail();
        expect((float) $out->quantity)->toBe(-4.0)->and($out->warehouse_id)->toBe($main->id)->and($out->reference_id)->toBe($transfer->id)
            ->and((float) $in->quantity)->toBe(4.0)->and($in->warehouse_id)->toBe($branch->id);
    });
});

it('refuses to move more than the source holds and changes nothing', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t2', onHand: 3);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t2')->post('/stock-transfers', transferPayload($main, $branch, $product, 5))
        ->assertSessionHasErrors('items');

    expect(stockAt($tenant, $product, $main))->toBe(3.0)->and(stockAt($tenant, $product, $branch))->toBe(0.0)
        ->and(app(TenantContext::class)->run($tenant, fn () => StockTransfer::query()->count()))->toBe(0);
});

it('moves nothing when one of several lines cannot be covered', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t3', onHand: 10);
    $other = app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => 'Salt', 'sku' => 'SALT-1', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true,
    ]));

    $payload = transferPayload($main, $branch, $product, 2);
    $payload['items'][] = ['product_id' => $other->id, 'quantity' => 1];

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t3')->post('/stock-transfers', $payload)->assertSessionHasErrors('items');

    expect(stockAt($tenant, $product, $main))->toBe(10.0)->and(stockAt($tenant, $product, $branch))->toBe(0.0);
});

it('does not move twice when the same form is submitted twice', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t4');
    $payload = transferPayload($main, $branch, $product, 2);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t4')->post('/stock-transfers', $payload)->assertRedirect();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t4')->post('/stock-transfers', $payload)->assertRedirect();

    expect(stockAt($tenant, $product, $main))->toBe(8.0)->and(stockAt($tenant, $product, $branch))->toBe(2.0)
        ->and(app(TenantContext::class)->run($tenant, fn () => StockTransfer::query()->count()))->toBe(1);
});

it('merges a product repeated on the form into one line', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t5');
    $payload = transferPayload($main, $branch, $product, 2);
    $payload['items'][] = ['product_id' => $product->id, 'quantity' => 3];

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t5')->post('/stock-transfers', $payload)->assertRedirect();

    expect(stockAt($tenant, $product, $branch))->toBe(5.0)
        ->and(app(TenantContext::class)->run($tenant, fn () => StockTransfer::query()->with('items')->first()->items))->toHaveCount(1);
});

it('rejects the same warehouse on both sides', function (): void {
    [$tenant, $user, $main, , $product] = transferShop('shop-t6');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t6')->post('/stock-transfers', transferPayload($main, $main, $product, 1))
        ->assertSessionHasErrors('to_warehouse_id');

    expect(stockAt($tenant, $product, $main))->toBe(10.0);
});

it('rejects an inactive warehouse', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t7');
    app(TenantContext::class)->run($tenant, fn () => $branch->update(['is_active' => false]));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t7')->post('/stock-transfers', transferPayload($main, $branch, $product, 1))
        ->assertSessionHasErrors('from_warehouse_id');
});

it('only moves whole units of products that do not allow decimals', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t8');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t8')->post('/stock-transfers', transferPayload($main, $branch, $product, 1.5))
        ->assertSessionHasErrors('items');

    expect(stockAt($tenant, $product, $main))->toBe(10.0);
});

it('allows fractions for weighed products', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-t9', productOverrides: ['is_weighed' => true]);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-t9')->post('/stock-transfers', transferPayload($main, $branch, $product, 1.5))->assertRedirect();

    expect(stockAt($tenant, $product, $branch))->toBe(1.5);
});

it('does not allow products that do not track stock', function (): void {
    [$tenant, $user, $main, $branch] = transferShop('shop-ta');
    $service = app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => 'Delivery', 'sku' => 'SVC', 'type' => 'service', 'cost_price' => 0, 'selling_price' => 1, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => false,
    ]));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ta')->post('/stock-transfers', transferPayload($main, $branch, $service, 1))
        ->assertSessionHasErrors('items.0.product_id');
});

it('forbids transfers without the manage permission but lets a viewer read the list', function (): void {
    [, $user, $main, $branch, $product] = transferShop('shop-tb', ['inventory.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-tb')->get('/stock-transfers')->assertOk();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-tb')->post('/stock-transfers', transferPayload($main, $branch, $product, 1))->assertForbidden();
});

it('keeps the transfer pages away from users without inventory access', function (): void {
    [, $user] = transferShop('shop-tc', ['products.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-tc')->get('/stock-transfers')->assertForbidden();
});

it('cannot move stock using another shop\'s warehouses', function (): void {
    [$tenant, $user, $main, , $product] = transferShop('shop-td');
    [, , , $foreignBranch] = transferShop('shop-tdb');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-td')->post('/stock-transfers', transferPayload($main, $foreignBranch, $product, 1))
        ->assertSessionHasErrors('to_warehouse_id');

    expect(stockAt($tenant, $product, $main))->toBe(10.0);
});

it('lists and shows transfers of this shop only', function (): void {
    [$tenant, $user, $main, $branch, $product] = transferShop('shop-te');
    [$other, $otherUser, $otherMain, $otherBranch, $otherProduct] = transferShop('shop-teb');

    $this->actingAs($otherUser)->withHeader('X-Tenant', 'shop-teb')->post('/stock-transfers', transferPayload($otherMain, $otherBranch, $otherProduct, 1, ['note' => 'their secret note']))->assertRedirect();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-te')->post('/stock-transfers', transferPayload($main, $branch, $product, 1, ['note' => 'our note']))->assertRedirect();

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-te')->get('/stock-transfers')->assertOk()->getContent();
    expect($html)->toContain('TR-000001')->toContain('Main Store')->not->toContain('their secret note');

    $id = app(TenantContext::class)->run($tenant, fn () => StockTransfer::query()->firstOrFail()->id);
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-te')->get('/stock-transfers/'.$id)->assertOk()->assertSee('our note')->assertSee('Soap');
});

it('shows the new transfer form, and explains when there is only one warehouse', function (): void {
    [, $user] = transferShop('shop-tf');
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-tf')->get('/stock-transfers/create')->assertOk()->assertSee('Move stock');
});

it('explains that two warehouses are needed', function (): void {
    $tenant = createTenant('shop-tg', 'basic');
    $user = createTenantUser($tenant, ['inventory.view', 'inventory.manage']);
    app(TenantContext::class)->run($tenant, fn () => Warehouse::create(['name' => 'Only', 'code' => 'ONE', 'is_default' => true, 'is_active' => true]));

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-tg')->get('/stock-transfers/create')->assertOk()->assertSee('at least two active warehouses');
});
