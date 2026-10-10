<?php

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function platformPayment(Tenant $tenant, array $overrides = []): TenantPayment
{
    $paidOn = Carbon::parse($overrides['paid_on'] ?? '2026-10-05');

    return TenantPayment::query()->create(array_merge([
        'tenant_id' => $tenant->getKey(),
        'idempotency_key' => (string) Str::uuid(),
        'plan' => 'medium',
        'amount' => 125_000,
        'currency' => 'TZS',
        'method' => 'cash',
        'reference' => 'PAY-'.Str::random(6),
        'note' => 'Subscription renewal',
        'months' => 1,
        'paid_on' => $paidOn->toDateString(),
        'period_start' => $paidOn->toDateString(),
        'period_end' => $paidOn->copy()->addMonth()->toDateString(),
    ], $overrides));
}

it('filters platform payment history and exports the same filtered rows', function (): void {
    Carbon::setTestNow('2026-10-10 12:00:00');
    $shopA = createTenant('payment-shop-a');
    $shopA->update(['name' => 'Payment Shop A']);
    $shopB = createTenant('payment-shop-b');
    $shopB->update(['name' => 'Payment Shop B']);

    $match = platformPayment($shopA, [
        'reference' => 'MATCH-CASH', 'plan_price_amount' => 175_000,
        'plan_name' => 'Medium plan', 'discount_amount' => 50_000, 'discount_reason' => 'Renewal promotion',
    ]);
    platformPayment($shopA, ['method' => 'mobile_money', 'reference' => 'OTHER-METHOD']);
    platformPayment($shopB, ['reference' => 'OTHER-SHOP']);
    platformPayment($shopA, ['currency' => 'USD', 'reference' => 'OTHER-CURRENCY']);
    platformPayment($shopA, ['paid_on' => '2026-09-30', 'reference' => 'OTHER-DATE']);

    $query = [
        'from' => '2026-10-01',
        'to' => '2026-10-10',
        'tenant_id' => $shopA->getKey(),
        'currency' => 'TZS',
        'method' => 'cash',
    ];

    $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->get(route('platform.payments.index', $query))
        ->assertOk()
        ->assertSee('Payment Shop A')
        ->assertSee('MATCH-CASH')
        ->assertSee('Medium plan')
        ->assertSee('Renewal promotion')
        ->assertSee('TZS 1,250.00')
        ->assertDontSee('OTHER-METHOD')
        ->assertDontSee('OTHER-SHOP')
        ->assertDontSee('OTHER-CURRENCY')
        ->assertDontSee('OTHER-DATE')
        ->assertSee(e(route('platform.payments.export', $query)), false);

    $export = $this->get(route('platform.payments.export', $query))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $export->streamedContent();

    expect($csv)->toContain('MATCH-CASH')
        ->toContain('1750.00')
        ->toContain('500.00')
        ->toContain('Renewal promotion')
        ->not->toContain('OTHER-METHOD')
        ->not->toContain('OTHER-SHOP')
        ->not->toContain('OTHER-CURRENCY')
        ->not->toContain('OTHER-DATE');

    expect($match->exists)->toBeTrue();
    Carbon::setTestNow();
});

it('validates transaction date ranges and restricts the page and export to super admins', function (): void {
    $tenant = createTenant('payment-denied');
    $shopUser = createTenantUser($tenant, ['dashboard.view']);
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->get('/platform/payments')->assertRedirect('/login');

    $this->actingAs($admin)
        ->get(route('platform.payments.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');

    $this->actingAs($shopUser)->withHeader('X-Tenant', 'payment-denied')
        ->get('/platform/payments')->assertForbidden();
    $this->actingAs($shopUser)->withHeader('X-Tenant', 'payment-denied')
        ->get('/platform/payments/export')->assertForbidden();
});
