<?php

use App\Models\TenantPayment;
use Illuminate\Support\Carbon;

function billingShop(string $slug, ?string $paidUntil = '2026-12-31', string $plan = 'medium', string $status = 'active'): array
{
    $tenant = createTenant($slug, $plan);
    $tenant->update(['paid_until' => $paidUntil, 'status' => $status]);
    $owner = createTenantUser($tenant, ['settings.manage', 'dashboard.view']);

    return [$tenant, $owner];
}

function billingPage(object $test, $user, string $slug)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->get('/billing');
}

function recordPayment(string $tenantId, string $key, array $overrides = []): TenantPayment
{
    return TenantPayment::query()->create(array_merge([
        'tenant_id' => $tenantId, 'idempotency_key' => $key, 'plan' => 'medium', 'plan_name' => 'Medium',
        'plan_price_amount' => 7000000, 'discount_amount' => 0, 'amount' => 7000000, 'currency' => 'TZS',
        'method' => 'mobile_money', 'reference' => 'MP123', 'months' => 1,
        'paid_on' => '2026-09-01', 'period_start' => '2026-09-01', 'period_end' => '2026-10-01',
    ], $overrides));
}

afterEach(fn () => Carbon::setTestNow());

it('shows the plan, paid-until date and a countdown', function () {
    Carbon::setTestNow('2026-12-20 10:00:00');
    [, $owner] = billingShop('bill-a');

    billingPage($this, $owner, 'bill-a')->assertOk()
        ->assertSee('data-billing-status', false)
        ->assertSee('data-deadline="2026-12-31"', false)
        ->assertSee('data-countdown="', false)
        ->assertSee('data-state="active"', false)
        ->assertSee('Medium');
});

it('lists every plan with its features and marks the current one', function () {
    [, $owner] = billingShop('bill-b', plan: 'basic');

    $page = billingPage($this, $owner, 'bill-b')->assertOk();
    $page->assertSee('data-plan="basic"', false)->assertSee('data-plan="medium"', false)->assertSee('data-plan="enterprise"', false);
    expect(substr_count($page->getContent(), 'data-current="1"'))->toBe(1);
});

it('explains the grace period and the read-only state, which can still open the page', function (string $now, string $state, string $text) {
    Carbon::setTestNow($now);
    [, $owner] = billingShop('bill-c', '2026-10-10');

    billingPage($this, $owner, 'bill-c')->assertOk()
        ->assertSee('data-state="'.$state.'"', false)
        ->assertSee($text);
})->with([
    'grace' => ['2026-10-12 09:00:00', 'grace', 'still works until 2026-10-17'],
    'read-only' => ['2026-10-20 09:00:00', 'readonly', 'grace period is over'],
]);

it('shows a trial shop its trial end and the trial note', function () {
    Carbon::setTestNow('2026-10-01 09:00:00');
    $tenant = createTenant('bill-t', 'basic');
    $tenant->update(['status' => 'trial', 'paid_until' => null, 'trial_ends_at' => '2026-10-15']);
    $owner = createTenantUser($tenant, ['settings.manage']);

    billingPage($this, $owner, 'bill-t')->assertOk()
        ->assertSee('Free trial')
        ->assertSee('data-deadline="2026-10-15"', false);
});

it('copes with a shop that has no end date', function () {
    [, $owner] = billingShop('bill-n', null);

    billingPage($this, $owner, 'bill-n')->assertOk()->assertSee('No end date is set')->assertDontSee('data-countdown="', false);
});

it('shows only this shop\'s payment history', function () {
    [$tenant, $owner] = billingShop('bill-h');
    [$other] = billingShop('bill-o');
    recordPayment($tenant->getKey(), 'key-mine-0001', ['reference' => 'MINE-REF']);
    recordPayment($other->getKey(), 'key-theirs-0001', ['reference' => 'THEIRS-REF']);

    billingPage($this, $owner, 'bill-h')->assertOk()
        ->assertSee('MINE-REF')
        ->assertSee('TZS 70,000.00')
        ->assertSee('2026-09-01 → 2026-10-01')
        ->assertDontSee('THEIRS-REF');
});

it('says so when there are no payments yet', function () {
    [, $owner] = billingShop('bill-e');

    billingPage($this, $owner, 'bill-e')->assertSee('No payments recorded yet');
});

it('shows only the payment methods that are configured, with the shop name as reference', function () {
    config(['billing.mobile.mpesa.number' => '123456', 'billing.mobile.mixx.number' => null, 'billing.mobile.airtel.number' => '', 'billing.mobile.halopesa.number' => null]);
    config(['billing.bank.account' => '0150000000', 'billing.bank.name' => 'CRDB', 'billing.bank.branch' => null]);
    [$tenant, $owner] = billingShop('bill-m');

    $this->actingAs($owner)->withHeader('X-Tenant', 'bill-m')->get('/billing')->assertOk()
        ->assertSee('data-method="mpesa"', false)
        ->assertSee('123456')
        ->assertSee('*150*00#')
        ->assertSee('data-method="bank"', false)
        ->assertSee('0150000000')
        ->assertDontSee('data-method="mixx"', false)
        ->assertDontSee('data-method="airtel"', false)
        ->assertSee($tenant->name);
});

it('does not invent payment details when none are configured', function () {
    config(['billing.mobile.mpesa.number' => null, 'billing.mobile.mixx.number' => null, 'billing.mobile.airtel.number' => null, 'billing.mobile.halopesa.number' => null, 'billing.bank.account' => null]);
    [, $owner] = billingShop('bill-x');

    billingPage($this, $owner, 'bill-x')->assertOk()->assertSee('data-no-methods', false)->assertDontSee('data-method=', false);
});

it('only shows the contact buttons that are set', function () {
    config(['plans.contact.phone' => '+255 700 000 000', 'plans.contact.email' => null, 'billing.whatsapp' => '255700000000']);
    [, $owner] = billingShop('bill-k');

    billingPage($this, $owner, 'bill-k')->assertSee('tel:+255700000000', false)->assertSee('https://wa.me/255700000000', false)->assertDontSee('mailto:', false);
});

it('is for people who can manage settings only', function () {
    [$tenant] = billingShop('bill-p');
    $cashier = createTenantUser($tenant, ['sales.view']);

    billingPage($this, $cashier, 'bill-p')->assertForbidden();
});

it('shows a Swahili page', function () {
    [, $owner] = billingShop('bill-s');
    $owner->update(['locale' => 'sw']);

    billingPage($this, $owner, 'bill-s')->assertOk()->assertSee('Jinsi ya kulipa')->assertSee('Historia ya malipo');
});

it('adds the sidebar entry for the owner', function () {
    [, $owner] = billingShop('bill-l');

    $this->actingAs($owner)->withHeader('X-Tenant', 'bill-l')->get('/dashboard')->assertSee(route('billing.index'), false);
});

it('links the renewal reminder to the billing page', function () {
    Carbon::setTestNow('2026-12-28 09:00:00');
    [, $owner] = billingShop('bill-r');

    $this->actingAs($owner)->withHeader('X-Tenant', 'bill-r')->get('/dashboard')->assertSee('data-billing-link', false);
});
