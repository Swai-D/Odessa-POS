<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Services\TenantOwnerService;
use App\Models\Tenant;
use App\Notifications\ExpiryNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** @param  array<string, string|null>  $stock  product name => expiry date of its only batch */
function expiryShop(string $slug, array $stock): Tenant
{
    $tenant = app(ProvisionTenantAction::class)->handle([
        'name' => ucfirst($slug), 'slug' => $slug, 'plan' => 'enterprise', 'status' => 'active',
        'owner_name' => 'Owner '.$slug, 'owner_email' => "owner@{$slug}.test", 'owner_password' => 'secret-pass-123',
    ]);
    app(TenantOwnerService::class)->find($tenant)?->forceFill(['phone' => '0712345678'])->save();

    app(TenantContext::class)->run($tenant, function () use ($stock): void {
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        foreach ($stock as $name => $expiry) {
            $product = Product::create([
                'name' => $name, 'sku' => strtoupper(substr($name, 0, 3)).rand(100, 999), 'type' => 'standard', 'cost_price' => 1000, 'selling_price' => 2000,
                'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'track_expiry' => true,
            ]);
            app(StockService::class)->move($product, $warehouse, 5, StockMovement::PURCHASE, unitCost: 1000, lot: ['expiry' => $expiry]);
        }
    });

    return $tenant;
}

afterEach(fn () => Carbon::setTestNow());

it('tells the owner which products have expired or expire within 30 days', function () {
    Notification::fake();
    expiryShop('exp-a', [
        'Milk' => now()->subDays(2)->toDateString(),
        'Yogurt' => now()->addDays(10)->toDateString(),
        'Rice' => now()->addDays(200)->toDateString(),
        'Salt' => null,
    ]);

    $this->artisan('stock:expiry-alerts')->assertSuccessful();

    Notification::assertSentOnDemandTimes(ExpiryNotification::class, 1);
    Notification::assertSentOnDemand(ExpiryNotification::class, fn (ExpiryNotification $n, array $c, AnonymousNotifiable $to) => $n->count === 2
        && $n->expired === 1
        && $n->names === 'Milk, Yogurt'
        && $to->routes['mail'] === 'owner@exp-a.test'
        && $to->routes['sms'] === '0712345678');
});

it('says nothing when nothing is close to expiring', function () {
    Notification::fake();
    expiryShop('exp-b', ['Rice' => now()->addDays(200)->toDateString()]);

    $this->artisan('stock:expiry-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not repeat an unchanged list every day, but repeats after a week', function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-14 08:40:00');
    expiryShop('exp-c', ['Milk' => '2026-10-20']);

    $this->artisan('stock:expiry-alerts')->assertSuccessful();
    Carbon::setTestNow('2026-10-15 08:40:00');
    $this->artisan('stock:expiry-alerts')->assertSuccessful();
    Notification::assertSentOnDemandTimes(ExpiryNotification::class, 1);

    Carbon::setTestNow('2026-10-22 08:40:00');
    $this->artisan('stock:expiry-alerts')->assertSuccessful();
    Notification::assertSentOnDemandTimes(ExpiryNotification::class, 2);
});

it('keeps each shop to its own products', function () {
    Notification::fake();
    expiryShop('exp-d', ['Milk' => now()->addDays(3)->toDateString()]);
    expiryShop('exp-e', ['Bread' => now()->addDays(3)->toDateString()]);

    $this->artisan('stock:expiry-alerts')->assertSuccessful();

    Notification::assertSentOnDemandTimes(ExpiryNotification::class, 2);
    Notification::assertSentOnDemand(ExpiryNotification::class, fn (ExpiryNotification $n) => $n->shop === 'Exp-d' && $n->names === 'Milk');
    Notification::assertSentOnDemand(ExpiryNotification::class, fn (ExpiryNotification $n) => $n->shop === 'Exp-e' && $n->names === 'Bread');
});

it('ignores batches that are already sold out', function () {
    Notification::fake();
    $tenant = expiryShop('exp-f', ['Milk' => now()->addDays(3)->toDateString()]);
    app(TenantContext::class)->run($tenant, fn () => app(StockService::class)->move(
        Product::query()->where('name', 'Milk')->firstOrFail(), Warehouse::query()->where('code', 'MAIN')->firstOrFail(), -5, StockMovement::SALE,
    ));

    $this->artisan('stock:expiry-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});
