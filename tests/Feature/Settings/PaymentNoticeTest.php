<?php

use App\Models\PaymentNotice;
use App\Models\User;
use Illuminate\Support\Carbon;

function noticeShop(string $slug, ?string $paidUntil = '2026-12-31'): array
{
    $tenant = createTenant($slug, 'medium');
    $tenant->update(['paid_until' => $paidUntil]);
    $owner = createTenantUser($tenant, ['settings.manage']);

    return [$tenant, $owner];
}

function noticePayload(array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => 'notice-key-'.uniqid(),
        'method' => 'mpesa',
        'months' => 1,
        'amount' => '70000',
        'reference' => 'QWE123XYZ',
        'paid_on' => '2026-10-11',
        'note' => 'Paid by the owner',
    ], $overrides);
}

function sendNotice(object $test, $user, string $slug, array $overrides = [])
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/billing/notices', noticePayload($overrides));
}

afterEach(fn () => Carbon::setTestNow());

it('lets the owner tell us a payment was made', function () {
    Carbon::setTestNow('2026-10-12 09:00:00');
    [$tenant, $owner] = noticeShop('note-a');

    sendNotice($this, $owner, 'note-a')->assertRedirect(route('billing.index'))->assertSessionHas('status');

    $notice = PaymentNotice::query()->sole();
    expect($notice->tenant_id)->toBe($tenant->getKey())
        ->and($notice->amount)->toBe(7000000)
        ->and($notice->months)->toBe(1)
        ->and($notice->status)->toBe('pending')
        ->and($notice->user_id)->toBe($owner->getKey());
});

it('does not renew anything by itself', function () {
    Carbon::setTestNow('2026-10-12 09:00:00');
    [$tenant, $owner] = noticeShop('note-b');

    sendNotice($this, $owner, 'note-b')->assertRedirect();

    expect($tenant->fresh()->paid_until->format('Y-m-d'))->toBe('2026-12-31');
});

it('sends one notice when the form is double-submitted', function () {
    [, $owner] = noticeShop('note-c');

    sendNotice($this, $owner, 'note-c', ['idempotency_key' => 'same-key-0001']);
    sendNotice($this, $owner, 'note-c', ['idempotency_key' => 'same-key-0001']);

    expect(PaymentNotice::query()->count())->toBe(1);
});

it('refuses the same transaction id twice', function () {
    [, $owner] = noticeShop('note-d');

    sendNotice($this, $owner, 'note-d', ['reference' => 'DUPE-1234']);
    sendNotice($this, $owner, 'note-d', ['reference' => 'DUPE-1234'])->assertSessionHasErrors('reference');

    expect(PaymentNotice::query()->count())->toBe(1);
});

it('limits how many messages can wait at once', function () {
    [, $owner] = noticeShop('note-e');

    foreach (range(1, PaymentNotice::MAX_PENDING) as $i) {
        sendNotice($this, $owner, 'note-e', ['reference' => 'REF-000'.$i])->assertSessionHasNoErrors();
    }
    sendNotice($this, $owner, 'note-e', ['reference' => 'REF-EXTRA'])->assertSessionHasErrors('reference');

    expect(PaymentNotice::query()->count())->toBe(PaymentNotice::MAX_PENDING);
});

it('validates the form', function (array $bad, string $field) {
    [, $owner] = noticeShop('note-f');
    Carbon::setTestNow('2026-10-12 09:00:00');

    sendNotice($this, $owner, 'note-f', $bad)->assertSessionHasErrors($field);
    expect(PaymentNotice::query()->count())->toBe(0);
})->with([
    'unknown method' => [['method' => 'cheque'], 'method'],
    'odd period' => [['months' => 5], 'months'],
    'zero amount' => [['amount' => 0], 'amount'],
    'no transaction id' => [['reference' => ''], 'reference'],
    'future date' => [['paid_on' => '2027-01-01'], 'paid_on'],
]);

it('works for a read-only shop but not a suspended one', function () {
    Carbon::setTestNow('2026-12-20 09:00:00');
    [$lapsed, $owner] = noticeShop('note-g', '2026-10-01');

    sendNotice($this, $owner, 'note-g')->assertRedirect(route('billing.index'));
    expect(PaymentNotice::query()->count())->toBe(1);

    $lapsed->update(['status' => 'suspended']);
    sendNotice($this, $owner, 'note-g', ['reference' => 'AFTER-SUSP'])->assertForbidden();
    expect(PaymentNotice::query()->count())->toBe(1);
});

it('is for people who can manage settings only', function () {
    [$tenant] = noticeShop('note-h');
    $cashier = createTenantUser($tenant, ['sales.view']);

    sendNotice($this, $cashier, 'note-h')->assertForbidden();
    expect(PaymentNotice::query()->count())->toBe(0);
});

it('shows the form and the shop\'s own messages on the billing page', function () {
    [$tenant, $owner] = noticeShop('note-i');
    [$other, $otherOwner] = noticeShop('note-j');
    sendNotice($this, $owner, 'note-i', ['reference' => 'MINE-0001']);
    sendNotice($this, $otherOwner, 'note-j', ['reference' => 'THEIRS-0001']);

    $this->actingAs($owner)->withHeader('X-Tenant', 'note-i')->get('/billing')->assertOk()
        ->assertSee('data-billing-notice', false)
        ->assertSee('MINE-0001')
        ->assertSee('Waiting for confirmation')
        ->assertDontSee('THEIRS-0001');
});

it('gives customer care an inbox they can mark as handled', function () {
    [$tenant, $owner] = noticeShop('note-k');
    sendNotice($this, $owner, 'note-k', ['reference' => 'INBOX-0001']);
    $notice = PaymentNotice::query()->sole();
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)->get('/platform/notices')->assertOk()
        ->assertSee('INBOX-0001')
        ->assertSee('Note-k');

    $this->actingAs($admin)->post('/platform/notices/'.$notice->id.'/handle')->assertRedirect();
    expect($notice->fresh()->status)->toBe('handled')->and($notice->fresh()->handled_by)->toBe($admin->getKey());

    $this->actingAs($admin)->get('/platform/notices')->assertDontSee('INBOX-0001');
    $this->actingAs($admin)->get('/platform/notices?status=all')->assertSee('INBOX-0001');
});

it('keeps the inbox away from shop users', function () {
    [, $owner] = noticeShop('note-l');

    $this->actingAs($owner)->withHeader('X-Tenant', 'note-l')->get('/platform/notices')->assertForbidden();
});
