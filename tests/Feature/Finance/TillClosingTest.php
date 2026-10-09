<?php

use App\Domain\Finance\Models\TillClosing;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

function tillPermissions(): array
{
    return array_merge(posPermissions(), ['till.close']);
}

/** @return list<TillClosing> */
function tillClosings(Tenant $tenant): array
{
    return app(TenantContext::class)->run($tenant, fn () => TillClosing::query()->orderBy('id')->get()->all());
}

function closeTill(object $test, string $slug, User $user, array $overrides = [])
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/till-closings', $overrides + [
        'idempotency_key' => 'till-'.bin2hex(random_bytes(6)),
        'counted_cash' => '0',
        'float_kept' => '0',
    ]);
}

function tillCashSale(object $test, string $slug, User $user, $product, $warehouse, int $paid = 200000): void
{
    checkout($test, $slug, $user, cart($product, $warehouse, ['payments' => [['method' => 'cash', 'amount' => $paid]]]))->assertCreated();
}

it('expects the cash received and stores the difference against what was counted', function (): void {
    $tenant = createTenant('till-a');
    $user = createTenantUser($tenant, tillPermissions());
    [$product, $warehouse] = posFixture($tenant);
    tillCashSale($this, 'till-a', $user, $product, $warehouse);

    closeTill($this, 'till-a', $user, ['counted_cash' => '1990.00', 'float_kept' => '500.00', 'note' => 'short 10'])->assertRedirect();

    [$closing] = tillClosings($tenant);
    expect($closing->expected_cash)->toBe(200000)
        ->and($closing->counted_cash)->toBe(199000)
        ->and($closing->difference)->toBe(-1000)
        ->and($closing->float_kept)->toBe(50000)
        ->and($closing->sales_count)->toBe(1)
        ->and($closing->sales_total)->toBe(200000)
        ->and($closing->by_method)->toBe(['cash' => 200000])
        ->and($closing->note)->toBe('short 10');
});

it('starts the next closing where the last one ended and carries the float forward', function (): void {
    $tenant = createTenant('till-b');
    $user = createTenantUser($tenant, tillPermissions());
    [$product, $warehouse] = posFixture($tenant);

    tillCashSale($this, 'till-b', $user, $product, $warehouse);
    closeTill($this, 'till-b', $user, ['counted_cash' => '2000.00', 'float_kept' => '500.00'])->assertRedirect();

    $this->travel(5)->seconds();
    tillCashSale($this, 'till-b', $user, $product, $warehouse);
    closeTill($this, 'till-b', $user, ['counted_cash' => '2500.00', 'float_kept' => '0'])->assertRedirect();

    [, $second] = tillClosings($tenant);
    expect($second->opening_float)->toBe(50000)
        ->and($second->cash_sales)->toBe(200000)
        ->and($second->expected_cash)->toBe(250000)
        ->and($second->difference)->toBe(0)
        ->and($second->sales_count)->toBe(1);
});

it('reduces the expected cash by cash refunds', function (): void {
    $tenant = createTenant('till-c');
    $user = createTenantUser($tenant, tillPermissions());
    [$product, $warehouse] = posFixture($tenant);
    tillCashSale($this, 'till-c', $user, $product, $warehouse);

    app(TenantContext::class)->run($tenant, function () use ($user): void {
        SaleReturn::create([
            'sale_id' => Sale::query()->firstOrFail()->getKey(), 'user_id' => $user->getKey(), 'number' => 'RT-000001',
            'total' => 50000, 'credit_applied' => 0, 'refunded' => 50000, 'refund_method' => 'cash', 'returned_at' => now(),
        ]);
    });

    closeTill($this, 'till-c', $user, ['counted_cash' => '1500.00'])->assertRedirect();

    [$closing] = tillClosings($tenant);
    expect($closing->cash_refunds)->toBe(50000)->and($closing->expected_cash)->toBe(150000)->and($closing->difference)->toBe(0);
});

it('leaves out sales taken by other cashiers', function (): void {
    $tenant = createTenant('till-d');
    $mine = createTenantUser($tenant, tillPermissions());
    $other = createTenantUser($tenant, tillPermissions());
    [$product, $warehouse] = posFixture($tenant);
    tillCashSale($this, 'till-d', $other, $product, $warehouse);

    closeTill($this, 'till-d', $mine)->assertRedirect();

    [$closing] = tillClosings($tenant);
    expect($closing->expected_cash)->toBe(0)->and($closing->sales_count)->toBe(0);
});

it('counts mobile money in the snapshot but not in the expected cash', function (): void {
    $tenant = createTenant('till-e');
    $user = createTenantUser($tenant, tillPermissions());
    [$product, $warehouse] = posFixture($tenant);
    checkout($this, 'till-e', $user, cart($product, $warehouse, ['payments' => [['method' => 'mobile_money', 'amount' => 200000]]]))->assertCreated();

    closeTill($this, 'till-e', $user)->assertRedirect();

    [$closing] = tillClosings($tenant);
    expect($closing->expected_cash)->toBe(0)->and($closing->by_method)->toBe(['mobile_money' => 200000]);
});

it('ignores a double submit of the same form', function (): void {
    $tenant = createTenant('till-f');
    $user = createTenantUser($tenant, tillPermissions());
    $payload = ['idempotency_key' => 'same-key-123', 'counted_cash' => '0', 'float_kept' => '0'];

    closeTill($this, 'till-f', $user, $payload)->assertRedirect();
    closeTill($this, 'till-f', $user, $payload)->assertRedirect();

    expect(tillClosings($tenant))->toHaveCount(1);
});

it('refuses to keep more float than was counted', function (): void {
    $tenant = createTenant('till-g');
    $user = createTenantUser($tenant, tillPermissions());

    closeTill($this, 'till-g', $user, ['counted_cash' => '100', 'float_kept' => '200'])->assertSessionHasErrors('float_kept');

    expect(tillClosings($tenant))->toBeEmpty();
});

it('shows a cashier only their own closings but a till.view user everyone\'s', function (): void {
    $tenant = createTenant('till-h');
    $cashier = createTenantUser($tenant, tillPermissions());
    $other = createTenantUser($tenant, tillPermissions());
    $manager = createTenantUser($tenant, ['till.view']);

    closeTill($this, 'till-h', $cashier, ['note' => 'mine-note'])->assertRedirect();
    closeTill($this, 'till-h', $other, ['note' => 'theirs-note'])->assertRedirect();

    $this->actingAs($cashier)->withHeader('X-Tenant', 'till-h')->get('/till-closings')
        ->assertOk()->assertDontSee($other->name)->assertSee(route('till-closings.show', tillClosings($tenant)[0]));

    $this->actingAs($manager)->withHeader('X-Tenant', 'till-h')->get('/till-closings')
        ->assertOk()->assertSee($cashier->name)->assertSee($other->name)->assertDontSee(route('till-closings.create'));
});

it('forbids a cashier from opening somebody else\'s closing', function (): void {
    $tenant = createTenant('till-i');
    $cashier = createTenantUser($tenant, tillPermissions());
    $other = createTenantUser($tenant, tillPermissions());
    closeTill($this, 'till-i', $other)->assertRedirect();
    [$theirs] = tillClosings($tenant);

    $this->actingAs($cashier)->withHeader('X-Tenant', 'till-i')->get('/till-closings/'.$theirs->getKey())->assertForbidden();
});

it('lets a till.view user open any closing and print it', function (): void {
    $tenant = createTenant('till-j');
    $cashier = createTenantUser($tenant, tillPermissions());
    $manager = createTenantUser($tenant, ['till.view']);
    closeTill($this, 'till-j', $cashier)->assertRedirect();
    [$closing] = tillClosings($tenant);

    $this->actingAs($manager)->withHeader('X-Tenant', 'till-j')->get('/till-closings/'.$closing->getKey())
        ->assertOk()->assertSee($cashier->name);
});

it('does not let a user without till.close open the closing form or close', function (): void {
    $tenant = createTenant('till-k');
    $viewer = createTenantUser($tenant, ['till.view']);

    $this->actingAs($viewer)->withHeader('X-Tenant', 'till-k')->get('/till-closings/create')->assertForbidden();
});

it('forbids the whole area without any till permission', function (): void {
    $tenant = createTenant('till-l');
    $user = createTenantUser($tenant, ['sales.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'till-l')->get('/till-closings')->assertForbidden();
});

it('keeps closings of other shops out of reach', function (): void {
    $a = createTenant('till-m1');
    $b = createTenant('till-m2');
    $userA = createTenantUser($a, tillPermissions());
    $userB = createTenantUser($b, ['till.view']);
    closeTill($this, 'till-m1', $userA)->assertRedirect();
    [$closing] = tillClosings($a);

    $this->actingAs($userB)->withHeader('X-Tenant', 'till-m2')->get('/till-closings/'.$closing->getKey())->assertNotFound();
});

it('shows the sidebar entry to a cashier who only holds till.close', function (): void {
    $tenant = createTenant('till-n', 'basic');
    $user = createTenantUser($tenant, ['dashboard.view', 'till.close']);

    $this->actingAs($user)->withHeader('X-Tenant', 'till-n')->get('/dashboard')
        ->assertOk()->assertSee(route('till-closings.index'));
});

it('works on the Basic plan', function (): void {
    $tenant = createTenant('till-o', 'basic');
    $user = createTenantUser($tenant, ['till.close']);

    $this->actingAs($user)->withHeader('X-Tenant', 'till-o')->get('/till-closings/create')->assertOk()->assertSee(__('till.expected'));
});
