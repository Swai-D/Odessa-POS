<?php

use App\Support\Subscription;
use Illuminate\Support\Carbon;

function shopWithDeadline(string $slug, ?string $paidUntil, string $status = 'active'): array
{
    $tenant = createTenant($slug, 'enterprise');
    $tenant->update(['paid_until' => $paidUntil, 'status' => $status]);
    $user = createTenantUser($tenant, ['dashboard.view', 'brands.view', 'brands.manage']);

    return [$tenant, $user];
}

afterEach(fn () => Carbon::setTestNow());

it('derives the subscription state from the paid-until date', function (string $now, string $expected) {
    Carbon::setTestNow($now);
    [$tenant] = shopWithDeadline('shop-s', '2026-10-10');

    expect((new Subscription($tenant))->state())->toBe($expected);
})->with([
    'before' => ['2026-10-01 09:00:00', 'active'],
    'on the day' => ['2026-10-10 20:00:00', 'active'],
    'day after' => ['2026-10-11 08:00:00', 'grace'],
    'last grace day' => ['2026-10-17 20:00:00', 'grace'],
    'after grace' => ['2026-10-18 08:00:00', 'readonly'],
]);

it('has no deadline without a paid-until date and uses the trial end for trials', function () {
    Carbon::setTestNow('2026-10-20');
    [$open] = shopWithDeadline('shop-open', null);
    [$trial] = shopWithDeadline('shop-trial', null, 'trial');
    $trial->update(['trial_ends_at' => '2026-10-01']);

    expect((new Subscription($open))->state())->toBe('active')
        ->and((new Subscription($trial->refresh()))->state())->toBe('readonly');
});

it('keeps an expired shop readable but blocks changes', function () {
    Carbon::setTestNow('2026-11-01');
    [, $user] = shopWithDeadline('shop-ro', '2026-10-10');
    $h = ['X-Tenant' => 'shop-ro'];

    $this->actingAs($user)->withHeaders($h)->get('/brands')->assertOk()
        ->assertSee(__('subscription.readonly_banner'));
    $this->actingAs($user)->withHeaders($h)->post('/brands', ['name' => 'Acme', 'is_active' => 1])->assertForbidden();
    $this->actingAs($user)->withHeaders($h)->postJson('/brands', ['name' => 'Acme'])->assertForbidden();
});

it('keeps working during the grace period with a warning', function () {
    Carbon::setTestNow('2026-10-12');
    [, $user] = shopWithDeadline('shop-gr', '2026-10-10');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-gr')->post('/brands', ['name' => 'Acme', 'is_active' => 1])->assertSessionHasNoErrors();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-gr')->get('/brands')->assertSee(__('subscription.grace_banner', ['date' => '2026-10-17']));
});

it('switches a suspended shop off', function () {
    [, $user] = shopWithDeadline('shop-su', null, 'suspended');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-su')->get('/brands')->assertForbidden();
});

it('shows a renewal reminder shortly before the deadline', function () {
    Carbon::setTestNow('2026-10-07');
    [, $user] = shopWithDeadline('shop-rm', '2026-10-10');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-rm')->get('/brands')->assertOk()
        ->assertSee(__('subscription.renew_banner', ['date' => '2026-10-10', 'days' => 3]));
});
