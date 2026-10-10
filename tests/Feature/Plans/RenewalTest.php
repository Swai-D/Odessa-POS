<?php

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Support\Subscription;
use Illuminate\Support\Carbon;

function renewalPayload(array $overrides = []): array
{
    return $overrides + [
        'months' => 1, 'discount_amount' => '0.00', 'method' => 'mobile_money',
        'paid_on' => Carbon::today()->format('Y-m-d'), 'reference' => 'MP123456',
        'idempotency_key' => 'renew-'.bin2hex(random_bytes(6)),
    ];
}

function renewableShop(string $slug, ?Carbon $paidUntil, string $status = 'active'): Tenant
{
    SubscriptionPlan::query()->where('code', 'medium')->update(['monthly_price' => 5_000_000, 'annual_price' => 50_000_000]);
    $tenant = createTenant($slug, 'medium');
    $tenant->update(['paid_until' => $paidUntil, 'status' => $status]);

    return $tenant;
}

function renewalUrl(Tenant $tenant): string
{
    return "/platform/tenants/{$tenant->getKey()}/renewals";
}

it('extends a paid-up shop from its current paid-until date and records the payment', function (): void {
    $start = Carbon::today()->addDays(10);
    $tenant = renewableShop('shop-n1', $start);

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload([
        'months' => 12,
        'amount' => '1.00',
        'discount_amount' => '50000.00',
        'discount_reason' => 'Annual renewal offer',
    ]))
        ->assertRedirect(route('platform.tenants.edit', $tenant));

    $expected = $start->copy()->addMonthsNoOverflow(12)->format('Y-m-d');
    $payment = TenantPayment::query()->firstOrFail();

    expect($tenant->fresh()->paid_until->format('Y-m-d'))->toBe($expected)
        ->and($payment->amount)->toBe(45_000_000)
        ->and($payment->plan_price_amount)->toBe(50_000_000)
        ->and($payment->discount_amount)->toBe(5_000_000)
        ->and($payment->discount_reason)->toBe('Annual renewal offer')
        ->and($payment->currency)->toBe('TZS')
        ->and($payment->plan)->toBe('medium')
        ->and($payment->plan_name)->toBe('Medium')
        ->and($payment->period_start->format('Y-m-d'))->toBe($start->format('Y-m-d'))
        ->and($payment->period_end->format('Y-m-d'))->toBe($expected)
        ->and($payment->reference)->toBe('MP123456');
});

it('starts a lapsed shop from the payment day, not from the old deadline', function (): void {
    $tenant = renewableShop('shop-n2', Carbon::today()->subDays(40));
    expect((new Subscription($tenant))->state())->toBe(Subscription::READONLY);

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    $fresh = $tenant->fresh();
    expect($fresh->paid_until->format('Y-m-d'))->toBe(Carbon::today()->addMonthsNoOverflow(1)->format('Y-m-d'))
        ->and((new Subscription($fresh))->state())->toBe(Subscription::ACTIVE);
});

it('makes a shop on trial active when it pays', function (): void {
    $tenant = renewableShop('shop-n3', null, 'trial');

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    expect($tenant->fresh()->status)->toBe('active')->and($tenant->fresh()->paid_until)->not->toBeNull();
});

it('leaves a suspended shop suspended', function (): void {
    $tenant = renewableShop('shop-n4', Carbon::today()->addDay(), 'suspended');

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    expect($tenant->fresh()->status)->toBe('suspended');
});

it('does not extend twice when the same form is submitted twice', function (): void {
    $tenant = renewableShop('shop-n5', Carbon::today()->addDays(5));
    $payload = renewalPayload();

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), $payload)->assertRedirect();
    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), $payload)->assertRedirect();

    expect(TenantPayment::query()->count())->toBe(1)
        ->and($tenant->fresh()->paid_until->format('Y-m-d'))->toBe(Carbon::today()->addDays(5)->addMonthsNoOverflow(1)->format('Y-m-d'));
});

it('uses the changed catalog price only when the shop renews', function (): void {
    $paidUntil = Carbon::today()->addDays(5);
    $tenant = renewableShop('shop-price-change', $paidUntil);
    $plan = SubscriptionPlan::query()->where('code', 'medium')->firstOrFail();
    $plan->update(['monthly_price' => 8_000_000]);

    expect($tenant->fresh()->paid_until->format('Y-m-d'))->toBe($paidUntil->format('Y-m-d'));

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    $payment = TenantPayment::query()->firstOrFail();
    expect($payment->plan_price_amount)->toBe(8_000_000)
        ->and($payment->amount)->toBe(8_000_000)
        ->and($tenant->fresh()->paid_until->format('Y-m-d'))->toBe($paidUntil->copy()->addMonthNoOverflow()->format('Y-m-d'));
});

it('requires a configured price and a reasoned discount within the plan price', function (): void {
    $tenant = renewableShop('shop-discount-rules', Carbon::today()->addDay());
    $plan = SubscriptionPlan::query()->where('code', 'medium')->firstOrFail();

    $plan->update(['monthly_price' => null]);
    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->post(renewalUrl($tenant), renewalPayload())->assertSessionHasErrors('months');
    expect(TenantPayment::query()->count())->toBe(0);

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->get("/platform/tenants/{$tenant->getKey()}/edit")
        ->assertOk()
        ->assertSee(__('platform.plan_prices_incomplete'))
        ->assertDontSee('name="amount"', false)
        ->assertSee(__('platform.annual'));

    $plan->update(['monthly_price' => 5_000_000]);
    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->post(renewalUrl($tenant), renewalPayload(['discount_amount' => '5000']))
        ->assertSessionHasErrors('discount_reason');
    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->post(renewalUrl($tenant), renewalPayload([
            'discount_amount' => '50001', 'discount_reason' => 'Too much',
        ]))->assertSessionHasErrors('discount_amount');

    expect(TenantPayment::query()->count())->toBe(0);
});

it('forbids a shop user from recording payments', function (): void {
    $tenant = renewableShop('shop-n6', Carbon::today()->addDays(5));
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-n6')->post(renewalUrl($tenant), renewalPayload())->assertForbidden();

    expect(TenantPayment::query()->count())->toBe(0);
});

it('rejects bad months, a future payment date and an unknown method', function (): void {
    $tenant = renewableShop('shop-n7', Carbon::today()->addDays(5));

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload([
        'months' => 0, 'paid_on' => Carbon::today()->addDay()->format('Y-m-d'), 'method' => 'barter',
    ]))->assertSessionHasErrors(['months', 'paid_on', 'method']);

    expect(TenantPayment::query()->count())->toBe(0);
});

it('shows the renewal form and payment history on the shop page', function (): void {
    $tenant = renewableShop('shop-n8', Carbon::today()->addDays(5));
    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->post(renewalUrl($tenant), renewalPayload(['reference' => 'REF-HISTORY']))->assertRedirect();

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))->get("/platform/tenants/{$tenant->getKey()}/edit")
        ->assertOk()
        ->assertSee('Renew subscription')
        ->assertSee('Monthly')
        ->assertSee('Annual')
        ->assertSee('TZS 50,000.00')
        ->assertSee('REF-HISTORY')
        ->assertDontSee('name="amount"', false);
});
