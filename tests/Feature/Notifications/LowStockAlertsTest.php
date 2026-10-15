<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Services\TenantOwnerService;
use App\Models\Tenant;
use App\Notifications\LowStockNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

function lowStockShop(string $slug, float $alertQuantity): Tenant
{
    $tenant = app(ProvisionTenantAction::class)->handle([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'plan' => 'enterprise',
        'status' => 'active',
        'owner_name' => 'Owner '.$slug,
        'owner_email' => "owner@{$slug}.test",
        'owner_password' => 'secret-pass-123',
    ]);
    app(TenantOwnerService::class)->find($tenant)?->forceFill(['phone' => '0712345678'])->save();

    // Provisioning already created the shop's main warehouse; add one product with 10 in stock.
    app(TenantContext::class)->run($tenant, function () use ($slug, $alertQuantity): void {
        $product = Product::create([
            'name' => "Sugar {$slug}", 'sku' => 'SUG', 'type' => 'standard', 'cost_price' => 50000, 'selling_price' => 100000,
            'tax_rate' => 0, 'tax_inclusive' => true, 'track_stock' => true, 'alert_quantity' => $alertQuantity,
        ]);
        app(StockService::class)->move($product, Warehouse::query()->where('code', 'MAIN')->firstOrFail(), 10, StockMovement::OPENING);
    });

    return $tenant;
}

afterEach(fn () => Carbon::setTestNow());

it('tells the owner which products are running low', function () {
    Notification::fake();
    lowStockShop('low-a', 20);

    $this->artisan('stock:low-alerts')->assertSuccessful();

    Notification::assertSentOnDemandTimes(LowStockNotification::class, 1);
    Notification::assertSentOnDemand(LowStockNotification::class, fn (LowStockNotification $n, array $c, AnonymousNotifiable $to) => $n->count === 1
        && $n->names === 'Sugar low-a'
        && $to->routes['mail'] === 'owner@low-a.test'
        && $to->routes['sms'] === '0712345678');
});

it('says nothing when no product is below its alert quantity', function () {
    Notification::fake();
    lowStockShop('low-b', 5);

    $this->artisan('stock:low-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});

it('does not repeat an unchanged list every day, but repeats after a week', function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-14 08:30:00');
    lowStockShop('low-c', 20);

    $this->artisan('stock:low-alerts')->assertSuccessful();
    Carbon::setTestNow('2026-10-15 08:30:00');
    $this->artisan('stock:low-alerts')->assertSuccessful();
    Notification::assertSentOnDemandTimes(LowStockNotification::class, 1);

    Carbon::setTestNow('2026-10-22 08:30:00');
    $this->artisan('stock:low-alerts')->assertSuccessful();
    Notification::assertSentOnDemandTimes(LowStockNotification::class, 2);
});

it('keeps each shop to its own products', function () {
    Notification::fake();
    lowStockShop('low-d', 20);
    lowStockShop('low-e', 20);

    $this->artisan('stock:low-alerts')->assertSuccessful();

    Notification::assertSentOnDemandTimes(LowStockNotification::class, 2);
    Notification::assertSentOnDemand(LowStockNotification::class, fn (LowStockNotification $n) => $n->shop === 'Low-d' && $n->names === 'Sugar low-d');
    Notification::assertSentOnDemand(LowStockNotification::class, fn (LowStockNotification $n) => $n->shop === 'Low-e' && $n->names === 'Sugar low-e');
});
