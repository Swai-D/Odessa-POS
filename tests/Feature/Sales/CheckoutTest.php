<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

function posPermissions(): array
{
    return ['pos.access', 'sales.view', 'sales.manage', 'customers.view', 'customers.manage'];
}

/** @return array{0: Product, 1: Warehouse, 2: Customer} */
function posFixture(Tenant $tenant, int $stock = 10): array
{
    return app(TenantContext::class)->run($tenant, function () use ($stock): array {
        $product = Product::create([
            'name' => 'Soap', 'sku' => 'SOAP', 'type' => 'standard', 'cost_price' => 50000,
            'selling_price' => 100000, 'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true,
        ]);
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $customer = Customer::create(['name' => 'Asha', 'credit_limit' => 150000]);
        app(StockService::class)->move($product, $warehouse, $stock, StockMovement::OPENING);

        return [$product, $warehouse, $customer];
    });
}

function checkout(object $test, string $slug, $user, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->postJson('/pos/checkout', $payload);
}

function cart(Product $product, Warehouse $warehouse, array $extra = []): array
{
    return array_merge([
        'idempotency_key' => 'key-'.uniqid(),
        'warehouse_id' => $warehouse->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], $extra);
}

it('charges server-side prices, deducts stock and gives change on cash', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    $response = checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 250000]],
    ]))->assertCreated();

    $response->assertJson(['number' => 'SL-000001', 'total' => 200000, 'amount_paid' => 200000, 'change_given' => 50000, 'payment_status' => 'paid']);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(app(StockService::class)->quantity($product, $warehouse))->toBe(8.0);
    });
});

it('returns the original sale when the same idempotency key is replayed', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $payload = cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => 200000]]]);

    checkout($this, 'shop-a', $user, $payload)->assertCreated();
    checkout($this, 'shop-a', $user, $payload)->assertOk()->assertJson(['number' => 'SL-000001']);

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(Sale::query()->count())->toBe(1)
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(8.0);
    });
});

it('rejects overpaying with a non-cash method', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'card', 'amount' => 300000]],
    ]))->assertUnprocessable();

    app(TenantContext::class)->run($tenant, fn () => expect(Sale::query()->count())->toBe(0));
});

it('refuses to sell more than is in stock and leaves no sale behind', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 1);

    checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertUnprocessable()->assertJsonValidationErrors('items');

    app(TenantContext::class)->run($tenant, function () use ($product, $warehouse): void {
        expect(Sale::query()->count())->toBe(0)
            ->and(app(StockService::class)->quantity($product, $warehouse))->toBe(1.0);
    });
});

it('allows a credit sale within the limit and blocks one above it', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse, $customer] = posFixture($tenant);

    checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]))->assertCreated()->assertJson(['balance_due' => 100000, 'payment_status' => 'unpaid']);

    // 100,000 already owed + 200,000 would exceed the 150,000 limit.
    checkout($this, 'shop-a', $user, cart($product, $warehouse, ['customer_id' => $customer->id]))
        ->assertUnprocessable();
});

it('requires a customer for a sale with a balance due', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'shop-a', $user, cart($product, $warehouse))->assertUnprocessable();
});

it('records a later payment against a credit sale', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse, $customer] = posFixture($tenant);

    $id = checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]))->json('id');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post("/sales/{$id}/payments", ['method' => 'cash', 'amount' => '400'])->assertSessionHasNoErrors();

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post("/sales/{$id}/payments", ['method' => 'cash', 'amount' => '600'])->assertSessionHasNoErrors();

    app(TenantContext::class)->run($tenant, function () use ($id): void {
        $sale = Sale::query()->findOrFail($id);
        expect($sale->balance_due)->toBe(0)->and($sale->payment_status)->toBe('paid');
    });

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')
        ->post("/sales/{$id}/payments", ['method' => 'cash', 'amount' => '1'])->assertSessionHasErrors('amount');
});

it('keeps sales numbered per tenant and isolated between tenants', function (): void {
    $a = createTenant('shop-a');
    $b = createTenant('shop-b');
    $userA = createTenantUser($a, posPermissions());
    $userB = createTenantUser($b, posPermissions());
    [$productA, $warehouseA] = posFixture($a);
    [$productB, $warehouseB] = posFixture($b);

    $paid = fn ($p, $w) => cart($p, $w, ['payments' => [['method' => 'cash', 'amount' => 200000]]]);

    $idA = checkout($this, 'shop-a', $userA, $paid($productA, $warehouseA))->assertCreated()->json('id');
    checkout($this, 'shop-b', $userB, $paid($productB, $warehouseB))->assertCreated()->assertJson(['number' => 'SL-000001']);

    $this->actingAs($userB)->withHeader('X-Tenant', 'shop-b')->get("/sales/{$idA}")->assertNotFound();

    // A tenant cannot sell another tenant's product.
    checkout($this, 'shop-b', $userB, $paid($productA, $warehouseB))->assertUnprocessable();
});

it('enforces permissions on the till and the sales list', function (): void {
    $tenant = createTenant('shop-a');
    $nobody = createTenantUser($tenant, []);
    $viewer = createTenantUser($tenant, ['sales.view']);
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'shop-a', $nobody, cart($product, $warehouse))->assertForbidden();
    $this->actingAs($nobody)->withHeader('X-Tenant', 'shop-a')->get('/pos')->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-a')->get('/pos')->assertForbidden();
    $this->actingAs($viewer)->withHeader('X-Tenant', 'shop-a')->get('/sales')->assertOk();
    $this->actingAs($nobody)->withHeader('X-Tenant', 'shop-a')->get('/sales')->assertForbidden();
});

it('renders the till, the held orders list, receipts and the PDF', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $id = checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->json('id');

    $get = fn (string $url) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get($url);

    $get('/pos')->assertOk()->assertSee('pos-grid', false);
    $get('/pos/products')->assertOk()->assertJsonFragment(['name' => 'Soap']);
    $get('/pos/held')->assertOk();
    $get('/sales')->assertOk()->assertSee('SL-000001');
    $get("/sales/{$id}")->assertOk();
    $get("/sales/{$id}?format=thermal")->assertOk();
    $get("/sales/{$id}/receipt.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
});
