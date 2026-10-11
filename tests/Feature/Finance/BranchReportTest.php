<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Services\BranchReport;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/** @return array{0: Tenant, 1: User, 2: Product, 3: Warehouse, 4: Warehouse} */
function branchShop(string $slug, string $plan = 'enterprise'): array
{
    $tenant = createTenant($slug, $plan);
    $user = createTenantUser($tenant, [...posPermissions(), 'reports.view']);
    [$product, $main] = posFixture($tenant);
    $second = app(TenantContext::class)->run($tenant, function () use ($product): Warehouse {
        $warehouse = Warehouse::create(['name' => 'Arusha', 'code' => 'ARU', 'is_default' => false]);
        app(StockService::class)->move($product, $warehouse, 5, StockMovement::OPENING);

        return $warehouse;
    });

    return [$tenant, $user, $product, $main, $second];
}

function branchRows(Tenant $tenant, ?Carbon $from = null): array
{
    return app(TenantContext::class)->run($tenant, fn () => (new BranchReport($from ?? Carbon::today()->subDays(6), Carbon::today()))->rows());
}

it('compares each branch by its own sales, profit and stock', function () {
    [$tenant, $user, $product, $main, $second] = branchShop('br-a');

    // Main sells 2 units, Arusha sells 1.
    checkout($this, 'br-a', $user, cart($product, $main, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    checkout($this, 'br-a', $user, cart($product, $second, [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 100000]],
    ]))->assertCreated();

    $rows = collect(branchRows($tenant))->keyBy('name');

    expect($rows['Main']['sales_count'])->toBe(1)
        ->and($rows['Main']['gross'])->toBe(200000)
        ->and($rows['Main']['net'])->toBe(200000)
        ->and($rows['Main']['gross_profit'])->toBe(100000)
        ->and($rows['Main']['average'])->toBe(200000)
        ->and($rows['Main']['stock_value'])->toBe(8 * 50000)
        ->and($rows['Arusha']['gross'])->toBe(100000)
        ->and($rows['Arusha']['gross_profit'])->toBe(50000)
        ->and($rows['Arusha']['stock_value'])->toBe(4 * 50000)
        ->and($rows['Main']['share'])->toBe(66.7)
        ->and($rows['Arusha']['share'])->toBe(33.3);
});

it('lists the best branch first and adds up the totals', function () {
    [$tenant, $user, $product, $main, $second] = branchShop('br-b');
    checkout($this, 'br-b', $user, cart($product, $second, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    checkout($this, 'br-b', $user, cart($product, $main, [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 100000]],
    ]))->assertCreated();

    $rows = branchRows($tenant);
    $totals = BranchReport::totals($rows);

    expect($rows[0]['name'])->toBe('Arusha')
        ->and($totals['gross'])->toBe(300000)
        ->and($totals['sales_count'])->toBe(2)
        ->and($totals['gross_profit'])->toBe(150000);
});

it('takes returns off the branch the sale was made from', function () {
    [$tenant, $user, $product, $main, $second] = branchShop('br-c');
    $saleId = soldSale($this, 'br-c', $user, [$product, $second]);
    $item = itemId($tenant, $saleId);

    postReturn($this, 'br-c', $user, $saleId, [
        'items' => [['sale_item_id' => $item, 'quantity' => 1]], 'refund_method' => 'cash', 'reason' => 'damaged',
    ])->assertSessionHasNoErrors();

    $rows = collect(branchRows($tenant))->keyBy('name');
    expect($rows['Arusha']['returns'])->toBe(100000)
        ->and($rows['Arusha']['net'])->toBe($rows['Arusha']['gross'] - 100000)
        ->and($rows['Main']['returns'])->toBe(0);
});

it('only counts sales inside the period', function () {
    [$tenant, $user, $product, $main] = branchShop('br-d');
    checkout($this, 'br-d', $user, cart($product, $main, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    app(TenantContext::class)->run($tenant, fn () => Sale::query()->update(['sold_at' => Carbon::today()->subDays(40)]));

    $rows = collect(branchRows($tenant))->keyBy('name');

    expect($rows['Main']['sales_count'])->toBe(0)->and($rows['Main']['gross'])->toBe(0);
});

it('never shows another shop\'s branches or sales', function () {
    [, $user] = branchShop('br-e');
    [$other, $otherUser, $otherProduct, $otherMain] = branchShop('br-f');
    checkout($this, 'br-f', $otherUser, cart($otherProduct, $otherMain, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    app(TenantContext::class)->run($other, fn () => Warehouse::create(['name' => 'Secret Branch', 'code' => 'SEC']));

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'br-e')->get('/reports/branches')->assertOk()->getContent();

    expect($page)->not->toContain('Secret Branch')->and($page)->toContain('data-branch="Main"');
});

it('hides an inactive branch that has nothing to show', function () {
    [$tenant, $user] = branchShop('br-g');
    app(TenantContext::class)->run($tenant, fn () => Warehouse::create(['name' => 'Closed', 'code' => 'CLO', 'is_active' => false]));

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'br-g')->get('/reports/branches')->assertOk()->getContent();

    expect($page)->not->toContain('data-branch="Closed"')->and($page)->toContain('data-branch="Arusha"');
});

it('downloads the branch report as CSV', function () {
    [, $user, $product, $main] = branchShop('br-h');
    checkout($this, 'br-h', $user, cart($product, $main, ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();

    $response = $this->actingAs($user)->withHeader('X-Tenant', 'br-h')->get('/reports/branches/export')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Main')->toContain('2000.00');
});

it('is only for Enterprise shops', function (string $plan) {
    [, $user] = branchShop('br-i', $plan);

    $this->actingAs($user)->withHeader('X-Tenant', 'br-i')->get('/reports/branches')->assertForbidden();
})->with(['basic', 'medium']);

it('needs the reports permission', function () {
    $tenant = createTenant('br-j');
    $user = createTenantUser($tenant, posPermissions());

    $this->actingAs($user)->withHeader('X-Tenant', 'br-j')->get('/reports/branches')->assertForbidden();
});

it('shows the entry locked in the Medium sidebar and open on Enterprise', function () {
    [, $medium] = branchShop('br-k', 'medium');
    $html = $this->actingAs($medium)->withHeader('X-Tenant', 'br-k')->get('/dashboard')->assertOk()->getContent();
    expect($html)->toContain('<a href="'.route('reports.branches').'" class="text-muted" data-locked="1"');

    [, $enterprise] = branchShop('br-l');
    $html = $this->actingAs($enterprise)->withHeader('X-Tenant', 'br-l')->get('/dashboard')->assertOk()->getContent();
    expect($html)->not->toContain('<a href="'.route('reports.branches').'" class="text-muted" data-locked="1"');
});
