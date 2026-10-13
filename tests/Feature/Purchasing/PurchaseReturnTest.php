<?php

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\PurchaseReturn;
use App\Support\Tenancy\TenantContext;

/** A shop with 10 Soap on the shelf, then 5 received from a supplier at 600.00 each (total 300000 minor units). */
function backShop(object $test, string $slug, array $extra = []): array
{
    $tenant = createTenant($slug);
    $user = createTenantUser($tenant, purchasingPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $supplier = makeSupplier($tenant);

    receiveGoods($test, $slug, $user, purchaseForm($tenant, $product, $warehouse, $supplier, $extra))->assertSessionHasNoErrors();

    $purchase = app(TenantContext::class)->run($tenant, fn () => Purchase::query()->with('items')->firstOrFail());

    return [$tenant, $user, $product, $warehouse, $purchase];
}

function sendBack(object $test, string $slug, $user, Purchase $purchase, float|int $quantity, array $extra = [])
{
    $itemId = $purchase->items->first()->id;

    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/purchases/'.$purchase->id.'/returns', array_merge([
        'items' => [['purchase_item_id' => $itemId, 'quantity' => $quantity]],
    ], $extra));
}

function backFresh($tenant, Purchase $purchase): Purchase
{
    return app(TenantContext::class)->run($tenant, fn () => Purchase::query()->findOrFail($purchase->id));
}

function shelf($tenant, $product, $warehouse): float
{
    return app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->quantity($product, $warehouse));
}

it('sends goods back: stock leaves and the debt to the supplier goes down', function (): void {
    [$tenant, $user, $product, $warehouse, $purchase] = backShop($this, 'back-a');

    sendBack($this, 'back-a', $user, $purchase, 2, ['reason' => 'Damaged'])->assertSessionHasNoErrors()->assertRedirect();

    $fresh = backFresh($tenant, $purchase);
    expect($fresh->returned_total)->toBe(120000)
        ->and($fresh->balance_due)->toBe(180000)
        ->and($fresh->payment_status)->toBe('unpaid')
        ->and(shelf($tenant, $product, $warehouse))->toBe(13.0);

    app(TenantContext::class)->run($tenant, function () use ($purchase): void {
        $return = PurchaseReturn::query()->firstOrFail();

        expect($return->number)->toBe('PR-000001')
            ->and($return->total)->toBe(120000)
            ->and($return->credit_applied)->toBe(120000)
            ->and($return->refunded)->toBe(0)
            ->and($return->refund_method)->toBeNull()
            ->and($return->reason)->toBe('Damaged')
            ->and($return->purchase_id)->toBe($purchase->id)
            ->and(StockMovement::query()->where('type', StockMovement::PURCHASE_RETURN)->count())->toBe(1);
    });
});

it('needs a refund method when the supplier gives money back', function (): void {
    [$tenant, $user, $product, $warehouse, $purchase] = backShop($this, 'back-b', ['payment_amount' => '3000.00', 'payment_method' => 'cash']);

    sendBack($this, 'back-b', $user, $purchase, 1)->assertSessionHasErrors('refund_method');

    expect(backFresh($tenant, $purchase)->returned_total)->toBe(0)
        ->and(shelf($tenant, $product, $warehouse))->toBe(15.0);

    sendBack($this, 'back-b', $user, $purchase, 1, ['refund_method' => 'cash'])->assertSessionHasNoErrors();

    app(TenantContext::class)->run($tenant, function (): void {
        $return = PurchaseReturn::query()->firstOrFail();

        expect($return->credit_applied)->toBe(0)->and($return->refunded)->toBe(60000)->and($return->refund_method)->toBe('cash');
    });
});

it('splits the value between debt reduced and money back', function (): void {
    // Paid 1000.00 of 3000.00, so 2000.00 is still owed. Sending back 4 units is worth 2400.00.
    [$tenant, $user, , , $purchase] = backShop($this, 'back-c', ['payment_amount' => '1000.00', 'payment_method' => 'cash']);

    sendBack($this, 'back-c', $user, $purchase, 4, ['refund_method' => 'cash'])->assertSessionHasNoErrors();

    $fresh = backFresh($tenant, $purchase);
    expect($fresh->balance_due)->toBe(0)->and($fresh->payment_status)->toBe('paid')->and($fresh->returned_total)->toBe(240000);

    app(TenantContext::class)->run($tenant, function (): void {
        $return = PurchaseReturn::query()->firstOrFail();

        expect($return->credit_applied)->toBe(200000)->and($return->refunded)->toBe(40000);
    });
});

it('cannot return more than was received, in one go or across several', function (): void {
    [$tenant, $user, , , $purchase] = backShop($this, 'back-d');

    sendBack($this, 'back-d', $user, $purchase, 6)->assertSessionHasErrors('items');

    sendBack($this, 'back-d', $user, $purchase, 3)->assertSessionHasNoErrors();
    sendBack($this, 'back-d', $user, $purchase, 3)->assertSessionHasErrors('items');
    sendBack($this, 'back-d', $user, $purchase, 2)->assertSessionHasNoErrors();

    // Returning the last units gives the exact remainder, so the whole purchase value comes back.
    expect(backFresh($tenant, $purchase)->returned_total)->toBe(300000)->and(backFresh($tenant, $purchase)->balance_due)->toBe(0);

    sendBack($this, 'back-d', $user, $purchase, 1)->assertSessionHasErrors('items');
});

it('refuses goods that were already sold and changes nothing', function (): void {
    [$tenant, $user, $product, $warehouse, $purchase] = backShop($this, 'back-e');

    // Only 3 of the 15 are left on the shelf, so 5 cannot go back.
    app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->move($product, $warehouse, -12, StockMovement::ADJUSTMENT_OUT));

    sendBack($this, 'back-e', $user, $purchase, 5)->assertSessionHasErrors('items');

    expect(backFresh($tenant, $purchase)->returned_total)->toBe(0)
        ->and(shelf($tenant, $product, $warehouse))->toBe(3.0)
        ->and(app(TenantContext::class)->run($tenant, fn () => PurchaseReturn::query()->count()))->toBe(0);
});

it('rejects a return with no quantity', function (): void {
    [, $user, , , $purchase] = backShop($this, 'back-f');

    sendBack($this, 'back-f', $user, $purchase, 0)->assertSessionHasErrors('items');
});

it('pays what is still owed after a return, and no more', function (): void {
    [$tenant, $user, , , $purchase] = backShop($this, 'back-g');

    sendBack($this, 'back-g', $user, $purchase, 2)->assertSessionHasNoErrors();

    $pay = fn (string $amount) => $this->actingAs($user)->withHeader('X-Tenant', 'back-g')
        ->post('/purchases/'.$purchase->id.'/payments', ['method' => 'cash', 'amount' => $amount]);

    $pay('1800.01')->assertSessionHasErrors('amount');
    $pay('1800.00')->assertSessionHasNoErrors();

    $fresh = backFresh($tenant, $purchase);
    expect($fresh->balance_due)->toBe(0)->and($fresh->payment_status)->toBe('paid')->and($fresh->amount_paid)->toBe(180000);
});

it('shows the returns on the purchase page', function (): void {
    [, $user, , , $purchase] = backShop($this, 'back-h');

    sendBack($this, 'back-h', $user, $purchase, 1, ['reason' => 'Wrong colour'])->assertSessionHasNoErrors();

    $this->actingAs($user)->withHeader('X-Tenant', 'back-h')->get('/purchases/'.$purchase->id)
        ->assertOk()->assertSee('PR-000001')->assertSee('Wrong colour')->assertSee('Return goods to supplier');
});

it('needs the manage permission', function (): void {
    [$tenant, , , , $purchase] = backShop($this, 'back-i');
    $viewer = createTenantUser($tenant, ['purchases.view']);

    sendBack($this, 'back-i', $viewer, $purchase, 1)->assertForbidden();

    expect(backFresh($tenant, $purchase)->returned_total)->toBe(0);
});

it('cannot return another shop\'s purchase', function (): void {
    [, , , , $purchase] = backShop($this, 'back-j');
    $other = createTenant('back-j2');
    $user = createTenantUser($other, purchasingPermissions());

    sendBack($this, 'back-j2', $user, $purchase, 1)->assertNotFound();
});
