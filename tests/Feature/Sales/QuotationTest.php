<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Sales\Models\Quotation;
use App\Domain\Sales\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

function qtShop(string $slug, array $permissions = ['sales.view', 'sales.manage', 'products.view']): array
{
    $tenant = createTenant($slug);
    $user = createTenantUser($tenant, $permissions);
    [$product, $warehouse, $customer] = posFixture($tenant, 10);

    return [$tenant, $user, $product, $warehouse, $customer];
}

function qtPayload(Product $product, array $extra = []): array
{
    return array_merge([
        'idempotency_key' => 'quote-key-'.uniqid(),
        'customer_name' => 'Walk-in prospect',
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
    ], $extra);
}

function qtSave(object $test, $user, string $slug, array $payload)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/quotations', $payload);
}

function qtTheOne(object $tenant): Quotation
{
    return app(TenantContext::class)->run($tenant, fn () => Quotation::query()->latest('id')->firstOrFail());
}

function qtStock(object $tenant, Product $product): float
{
    return app(TenantContext::class)->run($tenant, fn () => (float) ProductStock::query()->where('product_id', $product->id)->sum('quantity'));
}

afterEach(fn () => Carbon::setTestNow());

it('saves a quotation with prices worked out on the server and touches no stock', function () {
    [$tenant, $user, $product] = qtShop('qt-a');

    // A client that tries to send its own price is ignored: only product ids and quantities are read.
    qtSave($this, $user, 'qt-a', qtPayload($product, ['items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 1]]]))
        ->assertRedirect();

    $quotation = qtTheOne($tenant);
    expect($quotation->number)->toBe('QT-000001')
        ->and($quotation->total)->toBe(300000)
        ->and($quotation->status)->toBe('draft')
        ->and($quotation->customer_name)->toBe('Walk-in prospect')
        ->and(qtStock($tenant, $product))->toBe(10.0);
});

it('applies a percentage discount', function () {
    [$tenant, $user, $product] = qtShop('qt-b');

    qtSave($this, $user, 'qt-b', qtPayload($product, ['discount_percent' => 10]))->assertRedirect();

    $quotation = qtTheOne($tenant);
    expect($quotation->discount_total)->toBe(30000)->and($quotation->total)->toBe(270000);
});

it('does not make two quotations for a double click', function () {
    [$tenant, $user, $product] = qtShop('qt-c');
    $payload = qtPayload($product);

    qtSave($this, $user, 'qt-c', $payload)->assertRedirect();
    qtSave($this, $user, 'qt-c', $payload)->assertRedirect();

    expect(app(TenantContext::class)->run($tenant, fn () => Quotation::query()->count()))->toBe(1);
});

it('shows the list, the page and a PDF', function () {
    [$tenant, $user, $product] = qtShop('qt-d');
    qtSave($this, $user, 'qt-d', qtPayload($product))->assertRedirect();
    $quotation = qtTheOne($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'qt-d')->get('/quotations')->assertOk()->assertSee('QT-000001');
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-d')->get('/quotations/create')->assertOk();
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-d')->get("/quotations/{$quotation->id}")->assertOk()->assertSee('Soap');
    $pdf = $this->actingAs($user)->withHeader('X-Tenant', 'qt-d')->get("/quotations/{$quotation->id}/pdf");
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf');
});

it('converts into a paid sale through the normal checkout, once', function () {
    [$tenant, $user, $product, $warehouse] = qtShop('qt-e');
    qtSave($this, $user, 'qt-e', qtPayload($product))->assertRedirect();
    $quotation = qtTheOne($tenant);
    $url = "/quotations/{$quotation->id}/convert";
    $form = ['warehouse_id' => $warehouse->id, 'payment_amount' => '3000', 'payment_method' => 'cash'];

    $this->actingAs($user)->withHeader('X-Tenant', 'qt-e')->post($url, $form)->assertRedirect();
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-e')->post($url, $form)->assertRedirect();

    $sale = app(TenantContext::class)->run($tenant, fn () => Sale::query()->sole());
    $quotation = qtTheOne($tenant);
    expect($sale->total)->toBe(300000)
        ->and($sale->payment_status)->toBe('paid')
        ->and($quotation->status)->toBe('converted')
        ->and($quotation->sale_id)->toBe($sale->id)
        ->and(qtStock($tenant, $product))->toBe(7.0);
});

it('sells on credit to a known customer when nothing is paid', function () {
    [$tenant, $user, $product, $warehouse, $customer] = qtShop('qt-f');
    qtSave($this, $user, 'qt-f', qtPayload($product, ['customer_id' => $customer->id, 'customer_name' => null, 'items' => [['product_id' => $product->id, 'quantity' => 1]]]))->assertRedirect();
    $quotation = qtTheOne($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'qt-f')->post("/quotations/{$quotation->id}/convert", ['warehouse_id' => $warehouse->id])->assertRedirect();

    $sale = app(TenantContext::class)->run($tenant, fn () => Sale::query()->sole());
    expect($sale->customer_id)->toBe($customer->id)->and($sale->balance_due)->toBe(100000)->and($sale->payment_status)->toBe('unpaid');
});

it('refuses to sell on credit to someone who is not a customer, and leaves the quotation open', function () {
    [$tenant, $user, $product, $warehouse] = qtShop('qt-g');
    qtSave($this, $user, 'qt-g', qtPayload($product))->assertRedirect();
    $quotation = qtTheOne($tenant);

    $this->actingAs($user)->withHeader('X-Tenant', 'qt-g')->post("/quotations/{$quotation->id}/convert", ['warehouse_id' => $warehouse->id])->assertSessionHasErrors();

    expect(qtTheOne($tenant)->status)->toBe('draft')
        ->and(app(TenantContext::class)->run($tenant, fn () => Sale::query()->count()))->toBe(0)
        ->and(qtStock($tenant, $product))->toBe(10.0);
});

it('cannot convert an expired or declined quotation', function () {
    Carbon::setTestNow('2026-10-15 09:00:00');
    [$tenant, $user, $product, $warehouse] = qtShop('qt-h');
    qtSave($this, $user, 'qt-h', qtPayload($product, ['valid_until' => '2026-10-20']))->assertRedirect();
    $quotation = qtTheOne($tenant);
    $form = ['warehouse_id' => $warehouse->id, 'payment_amount' => '3000', 'payment_method' => 'cash'];

    Carbon::setTestNow('2026-10-25 09:00:00');
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-h')->post("/quotations/{$quotation->id}/convert", $form)->assertSessionHasErrors('status');

    Carbon::setTestNow('2026-10-16 09:00:00');
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-h')->post("/quotations/{$quotation->id}/status", ['status' => 'declined'])->assertRedirect();
    $this->actingAs($user)->withHeader('X-Tenant', 'qt-h')->post("/quotations/{$quotation->id}/convert", $form)->assertSessionHasErrors('status');

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->count()))->toBe(0);
});

it('warns when prices changed since the quotation', function () {
    [$tenant, $user, $product] = qtShop('qt-i');
    qtSave($this, $user, 'qt-i', qtPayload($product))->assertRedirect();
    $quotation = qtTheOne($tenant);
    app(TenantContext::class)->run($tenant, fn () => $product->update(['selling_price' => 120000]));

    $this->actingAs($user)->withHeader('X-Tenant', 'qt-i')->get("/quotations/{$quotation->id}")->assertOk()->assertSee('Prices have changed since this quotation');
});

it('lets a viewer look but not write or convert', function () {
    [$tenant, $owner, $product, $warehouse] = qtShop('qt-j');
    qtSave($this, $owner, 'qt-j', qtPayload($product))->assertRedirect();
    $quotation = qtTheOne($tenant);
    $viewer = createTenantUser($tenant, ['sales.view']);
    $as = fn () => $this->actingAs($viewer)->withHeader('X-Tenant', 'qt-j');

    $as()->get('/quotations')->assertOk();
    $as()->get('/quotations/create')->assertForbidden();
    $as()->post('/quotations', qtPayload($product))->assertForbidden();
    $as()->post("/quotations/{$quotation->id}/status", ['status' => 'sent'])->assertForbidden();
    $as()->post("/quotations/{$quotation->id}/convert", ['warehouse_id' => $warehouse->id, 'payment_amount' => '3000', 'payment_method' => 'cash'])->assertForbidden();
});

it('keeps one shop out of another shop quotations', function () {
    [$tenantA, $userA, $productA] = qtShop('qt-k');
    [, $userB] = qtShop('qt-l');
    qtSave($this, $userA, 'qt-k', qtPayload($productA))->assertRedirect();
    $quotation = qtTheOne($tenantA);

    $this->actingAs($userB)->withHeader('X-Tenant', 'qt-l')->get("/quotations/{$quotation->id}")->assertNotFound();
    $this->actingAs($userB)->withHeader('X-Tenant', 'qt-l')->get('/quotations')->assertOk()->assertDontSee('QT-000001');
});
