<?php

use App\Domain\Sales\Services\DashboardSummary;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

it('summarises only the shop\'s own sales, credit and low stock', function (): void {
    $tenant = createTenant('shop-d');
    $user = createTenantUser($tenant, [...posPermissions(), 'inventory.view', 'dashboard.view']);
    [$product, $warehouse, $customer] = posFixture($tenant);
    app(TenantContext::class)->run($tenant, fn () => $product->update(['alert_quantity' => 9]));

    checkout($this, 'shop-d', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertCreated();
    checkout($this, 'shop-d', $user, cart($product, $warehouse, [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'payments' => [],
    ]))->assertCreated();

    // Another shop's sale must not leak in.
    $other = createTenant('shop-e');
    $otherUser = createTenantUser($other, posPermissions());
    [$otherProduct, $otherWarehouse] = posFixture($other);
    checkout($this, 'shop-e', $otherUser, cart($otherProduct, $otherWarehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertCreated();

    $summary = app(TenantContext::class)->run($tenant, fn () => [
        'today' => (new DashboardSummary)->today(),
        'credit' => (new DashboardSummary)->outstandingCredit(),
        'low' => (new DashboardSummary)->lowStock()->pluck('name')->all(),
        'days' => (new DashboardSummary)->lastDays(),
    ]);

    expect($summary['today'])->toBe(['sales_count' => 2, 'sales_total' => 300000, 'credit_given' => 100000, 'returns_total' => 0])
        ->and($summary['credit'])->toBe(100000)
        ->and($summary['low'])->toBe(['Soap'])
        ->and(end($summary['days'])['total'])->toBe(300000)
        ->and($summary['days'])->toHaveCount(7);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-d')->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome, '.$user->name)
        ->assertSee('SL-000001')
        ->assertSee('Soap')
        ->assertDontSee('Apple Iphone');
});

it('shows only the greeting to a user without the sales permission', function (): void {
    $tenant = createTenant('shop-g');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-g')->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome, '.$user->name)
        ->assertDontSee(__('dashboard.recent_sales'));
});

it('shows this month\'s expenses on the dashboard only to those who may see them on a plan with expenses', function (): void {
    $tenant = createTenant('shop-dx', 'medium');
    $user = createTenantUser($tenant, ['sales.view', 'dashboard.view', 'expenses.view']);
    addExpense($tenant, 'Rent', 250000);
    addExpense($tenant, 'Old', 99999, Carbon::today()->subMonths(2)->format('Y-m-d'));
    $other = createTenant('shop-dy', 'medium');
    addExpense($other, 'Secret', 777700);

    $page = $this->actingAs($user)->withHeader('X-Tenant', 'shop-dx')->get('/dashboard')->assertOk();
    $page->assertSee('Expenses this month')->assertSee('2,500.00')->assertDontSee('7,777.00')->assertDontSee('999.99');
});

it('hides the expenses card without the permission', function (): void {
    $tenant = createTenant('shop-dz', 'medium');
    $user = createTenantUser($tenant, ['sales.view', 'dashboard.view']);
    addExpense($tenant, 'Rent', 250000);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-dz')->get('/dashboard')->assertOk()->assertDontSee('Expenses this month');
});

it('hides the expenses card on a plan without expenses', function (): void {
    $tenant = createTenant('shop-dw', 'basic');
    $user = createTenantUser($tenant, ['sales.view', 'dashboard.view', 'expenses.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-dw')->get('/dashboard')->assertOk()->assertDontSee('Expenses this month');
});
