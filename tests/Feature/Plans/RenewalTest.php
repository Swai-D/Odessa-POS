<?php

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Support\Subscription;
use Illuminate\Support\Carbon;

function renewalPayload(array $overrides = []): array
{
    return $overrides + [
        'months' => 1, 'amount' => '50000', 'method' => 'mobile_money',
        'paid_on' => Carbon::today()->format('Y-m-d'), 'reference' => 'MP123456',
        'idempotency_key' => 'renew-'.bin2hex(random_bytes(6)),
    ];
}

function renewableShop(string $slug, ?Carbon $paidUntil, string $status = 'active'): Tenant
{
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

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload(['months' => 2, 'amount' => '120000.50']))
        ->assertRedirect(route('platform.tenants.edit', $tenant));

    $expected = $start->copy()->addMonthsNoOverflow(2)->format('Y-m-d');
    $payment = TenantPayment::query()->firstOrFail();

    expect($tenant->fresh()->paid_until->format('Y-m-d'))->toBe($expected)
        ->and($payment->amount)->toBe(12000050)
        ->and($payment->plan)->toBe('medium')
        ->and($payment->period_start->format('Y-m-d'))->toBe($start->format('Y-m-d'))
        ->and($payment->period_end->format('Y-m-d'))->toBe($expected)
        ->and($payment->reference)->toBe('MP123456');
});

it('starts a lapsed shop from the payment day, not from the old deadline', function (): void {
    $tenant = renewableShop('shop-n2', Carbon::today()->subDays(40));
    expect((new Subscription($tenant))->state())->toBe(Subscription::READONLY);

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    $fresh = $tenant->fresh();
    expect($fresh->paid_until->format('Y-m-d'))->toBe(Carbon::today()->addMonthsNoOverflow(1)->format('Y-m-d'))
        ->and((new Subscription($fresh))->state())->toBe(Subscription::ACTIVE);
});

it('makes a shop on trial active when it pays', function (): void {
    $tenant = renewableShop('shop-n3', null, 'trial');

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    expect($tenant->fresh()->status)->toBe('active')->and($tenant->fresh()->paid_until)->not->toBeNull();
});

it('leaves a suspended shop suspended', function (): void {
    $tenant = renewableShop('shop-n4', Carbon::today()->addDay(), 'suspended');

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload())->assertRedirect();

    expect($tenant->fresh()->status)->toBe('suspended');
});

it('does not extend twice when the same form is submitted twice', function (): void {
    $tenant = renewableShop('shop-n5', Carbon::today()->addDays(5));
    $payload = renewalPayload();

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), $payload)->assertRedirect();
    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), $payload)->assertRedirect();

    expect(TenantPayment::query()->count())->toBe(1)
        ->and($tenant->fresh()->paid_until->format('Y-m-d'))->toBe(Carbon::today()->addDays(5)->addMonthsNoOverflow(1)->format('Y-m-d'));
});

it('forbids a shop user from recording payments', function (): void {
    $tenant = renewableShop('shop-n6', Carbon::today()->addDays(5));
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-n6')->post(renewalUrl($tenant), renewalPayload())->assertForbidden();

    expect(TenantPayment::query()->count())->toBe(0);
});

it('rejects bad months, a future payment date and an unknown method', function (): void {
    $tenant = renewableShop('shop-n7', Carbon::today()->addDays(5));

    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload([
        'months' => 0, 'paid_on' => Carbon::today()->addDay()->format('Y-m-d'), 'method' => 'barter',
    ]))->assertSessionHasErrors(['months', 'paid_on', 'method']);

    expect(TenantPayment::query()->count())->toBe(0);
});

it('shows the renewal form and payment history on the shop page', function (): void {
    $tenant = renewableShop('shop-n8', Carbon::today()->addDays(5));
    $this->actingAs(platformAdmin())->post(renewalUrl($tenant), renewalPayload(['reference' => 'REF-HISTORY']))->assertRedirect();

    $this->actingAs(platformAdmin())->get("/platform/tenants/{$tenant->getKey()}/edit")
        ->assertOk()->assertSee('Renew subscription')->assertSee('REF-HISTORY')->assertSee('50,000.00');
});
