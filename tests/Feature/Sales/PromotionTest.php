<?php

use App\Domain\Sales\Models\Promotion;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

function makePromotion(Tenant $tenant, array $attributes = []): Promotion
{
    return app(TenantContext::class)->run($tenant, fn () => Promotion::query()->create($attributes + [
        'name' => 'Promo', 'type' => 'percent', 'value' => 1000, 'scope' => 'all', 'is_active' => true,
    ]));
}

function promoSale(object $test, string $slug, $user, $product, $warehouse, array $extra = [], int $quantity = 2, int $paid = 1000000)
{
    return checkout($test, $slug, $user, cart($product, $warehouse, $extra + [
        'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        'payments' => [['method' => 'cash', 'amount' => $paid]],
    ]));
}

it('takes an automatic promotion off the sale and records it on the line', function () {
    $tenant = createTenant('pr-a');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $promotion = makePromotion($tenant, ['name' => 'Ten percent', 'value' => 1000]);

    promoSale($this, 'pr-a', $user, $product, $warehouse)->assertCreated()->assertJson(['total' => 180000]);

    app(TenantContext::class)->run($tenant, function () use ($promotion) {
        $sale = Sale::query()->with('items')->sole();
        $item = $sale->items->sole();

        expect($sale->promo_total)->toBe(20000)->and($sale->discount_total)->toBe(20000)->and($sale->subtotal)->toBe(200000)
            ->and($item->promotion_id)->toBe($promotion->id)->and($item->promotion_name)->toBe('Ten percent')
            ->and($item->promo_discount)->toBe(20000)->and($item->discount)->toBe(20000);
    });
});

it('gives the line the better of two promotions and never adds them together', function () {
    $tenant = createTenant('pr-b');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    makePromotion($tenant, ['name' => 'Small', 'value' => 500]);
    $big = makePromotion($tenant, ['name' => 'Big', 'type' => 'fixed', 'value' => 30000]);

    promoSale($this, 'pr-b', $user, $product, $warehouse)->assertCreated()->assertJson(['total' => 140000]);

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->with('items')->sole()->items->sole()->promotion_id))->toBe($big->id);
});

it('takes the cashier\'s order discount off what is left after the promotion', function () {
    $tenant = createTenant('pr-c');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    makePromotion($tenant, ['value' => 5000]); // 50% of 200000

    promoSale($this, 'pr-c', $user, $product, $warehouse, ['discount' => ['type' => 'percent', 'value' => 10]])
        ->assertCreated()->assertJson(['total' => 90000]);
});

it('applies buy two get one free on whole groups', function () {
    $tenant = createTenant('pr-d');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant, stock: 20);
    makePromotion($tenant, ['type' => 'bogo', 'buy_qty' => 2, 'get_qty' => 1, 'value' => 0]);

    promoSale($this, 'pr-d', $user, $product, $warehouse, [], quantity: 7)->assertCreated()->assertJson(['total' => 500000]);
});

it('ignores a promotion that is off, ended, not started or outside its days', function () {
    $tenant = createTenant('pr-e');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    makePromotion($tenant, ['is_active' => false]);
    makePromotion($tenant, ['ends_on' => now()->subDays(2)->toDateString()]);
    makePromotion($tenant, ['starts_on' => now()->addDays(2)->toDateString()]);
    $otherDay = (int) now('Africa/Dar_es_Salaam')->format('N') % 7 + 1;
    makePromotion($tenant, ['days' => (string) $otherDay]);

    promoSale($this, 'pr-e', $user, $product, $warehouse)->assertCreated()->assertJson(['total' => 200000]);
});

it('uses a coupon only when its code is given, and counts the use', function () {
    $tenant = createTenant('pr-f');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $coupon = makePromotion($tenant, ['name' => 'Welcome', 'code' => 'WELCOME', 'value' => 2000, 'max_uses' => 2]);

    promoSale($this, 'pr-f', $user, $product, $warehouse)->assertCreated()->assertJson(['total' => 200000]);
    promoSale($this, 'pr-f', $user, $product, $warehouse, ['coupon_code' => ' welcome '])->assertCreated()->assertJson(['total' => 160000]);

    app(TenantContext::class)->run($tenant, function () use ($coupon) {
        expect($coupon->fresh()->uses_count)->toBe(1)->and(Sale::query()->whereNotNull('coupon_code')->sole()->coupon_code)->toBe('WELCOME');
    });
});

it('refuses an unknown, used-up or inactive coupon and records no sale', function () {
    $tenant = createTenant('pr-g');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    makePromotion($tenant, ['code' => 'ONCE', 'max_uses' => 1, 'uses_count' => 1]);
    makePromotion($tenant, ['code' => 'OFF', 'is_active' => false]);

    foreach (['NOPE', 'ONCE', 'OFF'] as $code) {
        promoSale($this, 'pr-g', $user, $product, $warehouse, ['coupon_code' => $code])->assertUnprocessable()->assertJsonValidationErrors('coupon');
    }

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->count()))->toBe(0);
});

it('does not count a coupon use when a better automatic promotion won the line', function () {
    $tenant = createTenant('pr-h');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    $coupon = makePromotion($tenant, ['code' => 'SMALL', 'value' => 500]);
    makePromotion($tenant, ['name' => 'Better', 'value' => 3000]);

    promoSale($this, 'pr-h', $user, $product, $warehouse, ['coupon_code' => 'SMALL'])->assertCreated()->assertJson(['total' => 140000]);

    expect(app(TenantContext::class)->run($tenant, fn () => $coupon->fresh()->uses_count))->toBe(0);
});

it('does not let one shop\'s promotions or coupons touch another shop\'s sales', function () {
    $mine = createTenant('pr-i');
    $other = createTenant('pr-j');
    $user = createTenantUser($mine, posPermissions());
    [$product, $warehouse] = posFixture($mine);
    makePromotion($other, ['value' => 5000]);
    makePromotion($other, ['code' => 'THEIRS', 'value' => 5000]);

    promoSale($this, 'pr-i', $user, $product, $warehouse)->assertCreated()->assertJson(['total' => 200000]);
    promoSale($this, 'pr-i', $user, $product, $warehouse, ['coupon_code' => 'THEIRS'])->assertUnprocessable();
});

it('judges an offline sale by the promotions running when it was made', function () {
    $tenant = createTenant('pr-k');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);
    // Ended yesterday: running when the sale happened three days ago, not today.
    makePromotion($tenant, ['starts_on' => now()->subDays(5)->toDateString(), 'ends_on' => now()->subDay()->toDateString()]);

    promoSale($this, 'pr-k', $user, $product, $warehouse, ['offline' => ['sold_at' => now()->subDays(3)->toIso8601String(), 'expected_total' => 180000]])
        ->assertCreated()->assertJson(['total' => 180000, 'needs_review' => false]);
});

it('gives the till its running promotions and checks a coupon code', function () {
    $tenant = createTenant('pr-l');
    $user = createTenantUser($tenant, posPermissions());
    posFixture($tenant);
    makePromotion($tenant, ['name' => 'Auto']);
    makePromotion($tenant, ['name' => 'Secret', 'code' => 'SECRET22']);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'pr-l')->get('/pos')->assertOk()->getContent();
    expect($html)->toContain('Auto')->and($html)->not->toContain('SECRET22');

    $this->actingAs($user)->withHeader('X-Tenant', 'pr-l')->postJson('/pos/coupon', ['code' => 'secret22'])->assertOk()->assertJsonPath('data.code', 'SECRET22');
    $this->actingAs($user)->withHeader('X-Tenant', 'pr-l')->postJson('/pos/coupon', ['code' => 'WRONG'])->assertUnprocessable();

    $snapshot = $this->actingAs($user)->withHeader('X-Tenant', 'pr-l')->getJson('/pos/snapshot?warehouse=1')->assertOk();
    expect(collect($snapshot->json('promotions'))->pluck('name')->all())->toBe(['Auto']);
});

it('lets a manager create, change, switch off and delete a promotion', function () {
    $tenant = createTenant('pr-m');
    $user = createTenantUser($tenant, posPermissions());
    $this->actingAs($user)->withHeader('X-Tenant', 'pr-m');

    $this->post('/promotions', [
        'name' => 'Weekend 12.5', 'type' => 'percent', 'value' => '12.5', 'scope' => 'all', 'days' => ['6', '7'],
        'time_from' => '09:00', 'time_to' => '18:00', 'code' => 'wknd', 'max_uses' => 50, 'is_active' => 1,
    ])->assertRedirect('/promotions');

    $promotion = app(TenantContext::class)->run($tenant, fn () => Promotion::query()->sole());
    expect($promotion->value)->toBe(1250)->and($promotion->days)->toBe('6,7')->and($promotion->code)->toBe('WKND')->and($promotion->is_active)->toBeTrue();

    $this->put('/promotions/'.$promotion->id, ['name' => 'Fixed 500', 'type' => 'fixed', 'value' => '500', 'scope' => 'all', 'is_active' => 1])->assertRedirect('/promotions');
    expect(app(TenantContext::class)->run($tenant, fn () => Promotion::query()->sole()->value))->toBe(50000);

    $this->post('/promotions/'.$promotion->id.'/toggle')->assertRedirect();
    expect(app(TenantContext::class)->run($tenant, fn () => Promotion::query()->sole()->is_active))->toBeFalse();

    $this->get('/promotions')->assertOk()->assertSee('Fixed 500');
    $this->delete('/promotions/'.$promotion->id)->assertRedirect('/promotions');
    expect(app(TenantContext::class)->run($tenant, fn () => Promotion::query()->count()))->toBe(0);
});

it('checks the promotion form, including per-type fields, scope ids and unique codes', function () {
    $tenant = createTenant('pr-n');
    $user = createTenantUser($tenant, posPermissions());
    $this->actingAs($user)->withHeader('X-Tenant', 'pr-n');
    makePromotion($tenant, ['code' => 'TAKEN']);

    $this->post('/promotions', ['name' => 'x', 'type' => 'percent', 'value' => '150', 'scope' => 'all'])->assertSessionHasErrors('value');
    $this->post('/promotions', ['name' => 'x', 'type' => 'bogo', 'scope' => 'all'])->assertSessionHasErrors(['buy_qty', 'get_qty']);
    $this->post('/promotions', ['name' => 'x', 'type' => 'bulk', 'value' => '10', 'scope' => 'all'])->assertSessionHasErrors(['min_qty', 'value_kind']);
    $this->post('/promotions', ['name' => 'x', 'type' => 'percent', 'value' => '10', 'scope' => 'products'])->assertSessionHasErrors('scope_ids');
    $this->post('/promotions', ['name' => 'x', 'type' => 'percent', 'value' => '10', 'scope' => 'products', 'scope_ids' => [99999]])->assertSessionHasErrors('scope_ids.0');
    $this->post('/promotions', ['name' => 'x', 'type' => 'percent', 'value' => '10', 'scope' => 'all', 'code' => 'taken'])->assertSessionHasErrors('code');
    $this->post('/promotions', ['name' => 'x', 'type' => 'percent', 'value' => '10', 'scope' => 'all', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'])->assertSessionHasErrors('ends_on');
});

it('keeps promotions to people who may see or manage sales', function () {
    $tenant = createTenant('pr-o');
    $cashier = createTenantUser($tenant, ['pos.access', 'sales.view']);
    $nobody = createTenantUser($tenant, ['dashboard.view']);
    $promotion = makePromotion($tenant);

    $this->actingAs($cashier)->withHeader('X-Tenant', 'pr-o')->get('/promotions')->assertOk();
    $this->actingAs($cashier)->withHeader('X-Tenant', 'pr-o')->get('/promotions/create')->assertForbidden();
    $this->actingAs($cashier)->withHeader('X-Tenant', 'pr-o')->post('/promotions/'.$promotion->id.'/toggle')->assertForbidden();
    $this->actingAs($nobody)->withHeader('X-Tenant', 'pr-o')->get('/promotions')->assertForbidden();
});
