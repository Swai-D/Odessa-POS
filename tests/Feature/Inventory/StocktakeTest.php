<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use App\Support\Tenancy\TenantContext;

function countShop(string $slug): array
{
    $tenant = createTenant($slug);
    $user = createTenantUser($tenant, ['inventory.view', 'inventory.manage', 'sales.view']);
    [$product, $warehouse] = posFixture($tenant, 10);

    return [$tenant, $user, $product, $warehouse];
}

function startCount(object $test, $user, string $slug, $warehouse, array $extra = [])
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/stocktakes', array_merge([
        'idempotency_key' => 'count-key-'.uniqid(),
        'warehouse_id' => $warehouse->id,
    ], $extra));
}

function theCount(object $tenant): Stocktake
{
    return app(TenantContext::class)->run($tenant, fn () => Stocktake::query()->latest('id')->firstOrFail());
}

function countItem(object $tenant, Stocktake $count): StocktakeItem
{
    return app(TenantContext::class)->run($tenant, fn () => StocktakeItem::query()->where('stocktake_id', $count->id)->firstOrFail());
}

function stockOf(object $tenant, Product $product): float
{
    return app(TenantContext::class)->run($tenant, fn () => (float) ProductStock::query()->where('product_id', $product->id)->sum('quantity'));
}

it('opens a sheet with what the system expects', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-a');

    startCount($this, $user, 'cnt-a', $warehouse)->assertRedirect();

    $count = theCount($tenant);
    $item = countItem($tenant, $count);
    expect($count->number)->toBe('ST-000001')->and($count->status)->toBe('open')
        ->and((float) $item->expected)->toBe(10.0)->and($item->counted)->toBeNull()->and($item->sku)->toBe('SOAP');
});

it('sends one count when the form is double-submitted', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-b');

    startCount($this, $user, 'cnt-b', $warehouse, ['idempotency_key' => 'same-count-key']);
    startCount($this, $user, 'cnt-b', $warehouse, ['idempotency_key' => 'same-count-key']);

    expect(app(TenantContext::class)->run($tenant, fn () => Stocktake::query()->count()))->toBe(1);
});

it('allows only one open count per warehouse', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-c');

    startCount($this, $user, 'cnt-c', $warehouse);
    startCount($this, $user, 'cnt-c', $warehouse)->assertSessionHasErrors('warehouse_id');

    expect(app(TenantContext::class)->run($tenant, fn () => Stocktake::query()->count()))->toBe(1);
});

it('saves counts and shows the difference', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-d');
    startCount($this, $user, 'cnt-d', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-d')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '7']])->assertRedirect();

    expect((float) $item->fresh()->counted)->toBe(7.0);
    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-d')->get('/stocktakes/'.$count->id)->assertOk()->assertSee('data-item="SOAP"', false)->assertSee('-3');
});

it('corrects stock for the differences when completed, as adjustments in the ledger', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-e');
    startCount($this, $user, 'cnt-e', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-e')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '7'], 'complete' => 1])->assertRedirect(route('stocktakes.show', $count));

    expect(stockOf($tenant, $product))->toBe(7.0);
    $movement = app(TenantContext::class)->run($tenant, fn () => StockMovement::query()->where('type', StockMovement::ADJUSTMENT_OUT)->firstOrFail());
    expect((float) $movement->quantity)->toBe(-3.0)->and($movement->reference_type)->toBe((new Stocktake)->getMorphClass());
    expect($count->fresh()->status)->toBe('completed')->and((float) $item->fresh()->adjusted)->toBe(-3.0);
});

it('adds stock when more is counted than expected', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-f');
    startCount($this, $user, 'cnt-f', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-f')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '12'], 'complete' => 1]);

    expect(stockOf($tenant, $product))->toBe(12.0);
});

it('leaves products that were not counted alone', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-g');
    startCount($this, $user, 'cnt-g', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-g')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => ''], 'complete' => 1]);

    expect(stockOf($tenant, $product))->toBe(10.0)
        ->and(app(TenantContext::class)->run($tenant, fn () => StockMovement::query()->whereIn('type', [StockMovement::ADJUSTMENT_IN, StockMovement::ADJUSTMENT_OUT])->count()))->toBe(0);
});

it('keeps sales made while counting correct', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-h');
    startCount($this, $user, 'cnt-h', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    // Two sold while the shelves were being counted: 10 -> 8 in the system.
    $seller = createTenantUser($tenant, posPermissions());
    checkout($this, 'cnt-h', $seller, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    expect(stockOf($tenant, $product))->toBe(8.0);

    // The shelves held 10 when the count began, and 10 were counted: no difference, stock stays at 8.
    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-h')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '10'], 'complete' => 1]);

    expect(stockOf($tenant, $product))->toBe(8.0);
});

it('refuses a shortage it cannot post and changes nothing', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-i');
    startCount($this, $user, 'cnt-i', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    // Everything is sold after the count began, then the count says 0: 0 - 10 would leave the stock at -10.
    $seller = createTenantUser($tenant, posPermissions());
    checkout($this, 'cnt-i', $seller, cart($product, $warehouse, ['items' => [['product_id' => $product->id, 'quantity' => 10]], 'payments' => [['method' => 'cash', 'amount' => 1000000]]]))->assertCreated();

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-i')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '0'], 'complete' => 1])->assertSessionHasErrors('counts');

    expect($count->fresh()->status)->toBe('open')->and(stockOf($tenant, $product))->toBe(0.0);
});

it('cannot be completed twice or edited once finished', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-j');
    startCount($this, $user, 'cnt-j', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);
    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-j')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '7'], 'complete' => 1]);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-j')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '1'], 'complete' => 1])->assertSessionHasErrors('counts');

    expect(stockOf($tenant, $product))->toBe(7.0);
});

it('can be cancelled without touching stock', function () {
    [$tenant, $user, $product, $warehouse] = countShop('cnt-k');
    startCount($this, $user, 'cnt-k', $warehouse);
    $count = theCount($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-k')->post('/stocktakes/'.$count->id.'/cancel')->assertRedirect(route('stocktakes.index'));

    expect($count->fresh()->status)->toBe('cancelled')->and(stockOf($tenant, $product))->toBe(10.0);
    // A new count can be started afterwards.
    startCount($this, $user, 'cnt-k', $warehouse)->assertSessionHasNoErrors();
});

it('can be limited to one category', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-l');
    $categoryId = app(TenantContext::class)->run($tenant, function () {
        $category = Category::create(['name' => 'Drinks', 'slug' => 'drinks']);
        Product::create(['name' => 'Cola', 'sku' => 'COLA', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'category_id' => $category->id]);

        return $category->id;
    });

    startCount($this, $user, 'cnt-l', $warehouse, ['category_id' => $categoryId])->assertSessionHasNoErrors();

    $count = theCount($tenant);
    $skus = app(TenantContext::class)->run($tenant, fn () => StocktakeItem::query()->where('stocktake_id', $count->id)->pluck('sku')->all());
    expect($skus)->toBe(['COLA']);
});

it('validates whole quantities for products sold in whole units', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-m');
    startCount($this, $user, 'cnt-m', $warehouse);
    $count = theCount($tenant);
    $item = countItem($tenant, $count);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-m')->put('/stocktakes/'.$count->id, ['counts' => [$item->id => '2.5']])->assertSessionHasErrors();

    expect($item->fresh()->counted)->toBeNull();
});

it('is limited to people with the inventory permissions and to the shop', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-n');
    startCount($this, $user, 'cnt-n', $warehouse);
    $count = theCount($tenant);
    $viewer = createTenantUser($tenant, ['inventory.view']);
    $nobody = createTenantUser($tenant, ['sales.view']);
    [, $other] = countShop('cnt-o');

    $this->actingAs($viewer)->withHeader('X-Tenant', 'cnt-n')->get('/stocktakes/'.$count->id)->assertOk()->assertDontSee('data-complete', false);
    $this->actingAs($viewer)->withHeader('X-Tenant', 'cnt-n')->get('/stocktakes/create')->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'cnt-n')->post('/stocktakes/'.$count->id.'/cancel')->assertForbidden();
    $this->actingAs($nobody)->withHeader('X-Tenant', 'cnt-n')->get('/stocktakes')->assertForbidden();
    $this->actingAs($other)->withHeader('X-Tenant', 'cnt-o')->get('/stocktakes/'.$count->id)->assertNotFound();
});

it('lists counts with their progress and shows the menu entry', function () {
    [$tenant, $user, , $warehouse] = countShop('cnt-p');
    startCount($this, $user, 'cnt-p', $warehouse);

    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-p')->get('/stocktakes')->assertOk()->assertSee('data-stocktake="ST-000001"', false)->assertSee('0 / 1');
    $this->actingAs($user)->withHeader('X-Tenant', 'cnt-p')->get('/dashboard')->assertSee(route('stocktakes.index'), false);
});
