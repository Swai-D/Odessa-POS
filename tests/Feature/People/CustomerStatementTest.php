<?php

use App\Domain\People\Models\Customer;
use App\Support\Tenancy\TenantContext;

function statementShop(string $slug): array
{
    $tenant = createTenant($slug);
    $user = createTenantUser($tenant, [...posPermissions(), 'sales.manage']);
    $fixture = posFixture($tenant);

    return [$tenant, $user, $fixture];
}

function creditSale(object $test, string $slug, $user, array $fixture, int $paid = 50000): string
{
    [$product, $warehouse, $customer] = $fixture;

    return (string) checkout($test, $slug, $user, cart($product, $warehouse, [
        'customer_id' => $customer->id,
        'payments' => [['method' => 'cash', 'amount' => $paid]],
    ]))->assertCreated()->json('number');
}

it('shows what a customer bought, paid and still owes', function () {
    [, $user, $fixture] = statementShop('stmt-a');
    creditSale($this, 'stmt-a', $user, $fixture);

    $this->actingAs($user)->withHeader('X-Tenant', 'stmt-a')->get('/customers/'.$fixture[2]->id.'/statement')->assertOk()
        ->assertSee('data-statement', false)
        ->assertSee('Asha')
        ->assertSee('TZS 2,000.00')
        ->assertSee('TZS 500.00')
        ->assertSee('TZS 1,500.00');
});

it('can show only sales that are still owing', function () {
    [, $user, $fixture] = statementShop('stmt-b');
    creditSale($this, 'stmt-b', $user, $fixture, 50000);
    // A second sale paid in full.
    [$product, $warehouse, $customer] = $fixture;
    checkout($this, 'stmt-b', $user, cart($product, $warehouse, ['customer_id' => $customer->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 100000]]]))->assertCreated();

    $all = $this->actingAs($user)->withHeader('X-Tenant', 'stmt-b')->get('/customers/'.$customer->id.'/statement');
    $open = $this->actingAs($user)->withHeader('X-Tenant', 'stmt-b')->get('/customers/'.$customer->id.'/statement?open=1');

    expect(substr_count($all->getContent(), 'data-sale='))->toBe(2)
        ->and(substr_count($open->getContent(), 'data-sale='))->toBe(1);
});

it('filters by date', function () {
    [, $user, $fixture] = statementShop('stmt-c');
    creditSale($this, 'stmt-c', $user, $fixture);

    $this->actingAs($user)->withHeader('X-Tenant', 'stmt-c')->get('/customers/'.$fixture[2]->id.'/statement?from=2000-01-01&to=2000-01-31')->assertOk()->assertSee('data-empty', false);
});

it('says so when the customer has no sales', function () {
    [$tenant, $user] = statementShop('stmt-d');
    $customer = app(TenantContext::class)->run($tenant, fn () => Customer::create(['name' => 'Newcomer']));

    $this->actingAs($user)->withHeader('X-Tenant', 'stmt-d')->get('/customers/'.$customer->id.'/statement')->assertOk()->assertSee('data-empty', false);
});

it('never shows another shop\'s customer', function () {
    [, $userA, $fixtureA] = statementShop('stmt-e');
    [, $userB] = statementShop('stmt-f');

    $this->actingAs($userB)->withHeader('X-Tenant', 'stmt-f')->get('/customers/'.$fixtureA[2]->id.'/statement')->assertNotFound();
});

it('needs permission to view customers', function () {
    [$tenant, , $fixture] = statementShop('stmt-g');
    $nobody = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($nobody)->withHeader('X-Tenant', 'stmt-g')->get('/customers/'.$fixture[2]->id.'/statement')->assertForbidden();
});

it('adds a statement link to the customer list', function () {
    [, $user] = statementShop('stmt-h');

    $this->actingAs($user)->withHeader('X-Tenant', 'stmt-h')->get('/customers')->assertOk()->assertSee('data-view-link', false)->assertSee('Statement');
});
