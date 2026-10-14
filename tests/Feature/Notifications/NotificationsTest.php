<?php

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Actions\RenewSubscriptionAction;
use App\Domain\Settings\Services\TenantOwnerService;
use App\Models\PaymentNotice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Notifications\PaymentNoticeReceivedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\SubscriptionReminderNotification;
use App\Support\Sms\NextSmsGateway;
use App\Support\Sms\PhoneNumber;
use App\Support\Sms\SmsFailedException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

function notifyShop(string $slug, array $tenantAttributes = [], ?string $phone = '0712 345 678', string $locale = 'en'): Tenant
{
    $tenant = app(ProvisionTenantAction::class)->handle([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'plan' => 'medium',
        'status' => 'active',
        'owner_name' => 'Owner '.$slug,
        'owner_email' => "owner@{$slug}.test",
        'owner_password' => 'secret-pass-123',
    ]);
    $tenant->update($tenantAttributes);

    app(TenantOwnerService::class)->find($tenant)?->forceFill(['phone' => $phone, 'locale' => $locale])->save();

    return $tenant->refresh();
}

afterEach(fn () => Carbon::setTestNow());

it('turns the usual ways of typing a Tanzanian number into one format', function () {
    expect(PhoneNumber::normalize('0712 345 678'))->toBe('255712345678')
        ->and(PhoneNumber::normalize('+255 712-345-678'))->toBe('255712345678')
        ->and(PhoneNumber::normalize('712345678'))->toBe('255712345678')
        ->and(PhoneNumber::normalize('0612345678'))->toBe('255612345678')
        ->and(PhoneNumber::normalize('12345'))->toBeNull()
        ->and(PhoneNumber::normalize(null))->toBeNull();
});

it('sends an SMS to NextSMS with basic auth and the configured sender', function () {
    config([
        'notifications.sms.sender_id' => 'ODESSA',
        'notifications.sms.nextsms.username' => 'user',
        'notifications.sms.nextsms.password' => 'pass',
    ]);
    Http::fake(['*' => Http::response(['messages' => [['messageId' => 'm-1', 'status' => ['groupId' => 1]]]], 200)]);

    $result = (new NextSmsGateway)->send('255712345678', 'Hello', 'ref-1');

    expect($result->accepted)->toBeTrue()->and($result->reference)->toBe('m-1');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sms/v1/text/single')
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('user:pass'))
        && $request['from'] === 'ODESSA' && $request['to'] === '255712345678' && $request['text'] === 'Hello');
});

it('treats a rejected SMS as final and a provider outage as retryable', function () {
    // Responses are served in order: rejected, provider outage, bad request.
    Http::fake(['*' => Http::sequence()
        ->push(['messages' => [['status' => ['groupId' => 5, 'description' => 'REJECTED']]]], 200)
        ->push('oops', 503)
        ->push('bad', 400)]);

    expect((new NextSmsGateway)->send('255712345678', 'Hello')->accepted)->toBeFalse();
    expect(fn () => (new NextSmsGateway)->send('255712345678', 'Hello'))->toThrow(SmsFailedException::class);
    expect((new NextSmsGateway)->send('255712345678', 'Hello')->accepted)->toBeFalse();
});

it('reminds the owner three days before the subscription ends, once', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    Notification::fake();
    notifyShop('remind-a', ['paid_until' => '2026-10-17']);

    $this->artisan('subscriptions:remind')->assertSuccessful();
    $this->artisan('subscriptions:remind')->assertSuccessful();

    Notification::assertSentOnDemandTimes(SubscriptionReminderNotification::class, 1);
    Notification::assertSentOnDemand(SubscriptionReminderNotification::class, function (SubscriptionReminderNotification $n, array $channels, AnonymousNotifiable $to) {
        return $n->daysLeft === 3
            && $n->shop === 'Remind-a'
            && $to->routes['mail'] === 'owner@remind-a.test'
            && $to->routes['sms'] === '0712 345 678';
    });
});

it('does not remind on days that are not reminder days or for suspended shops', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    Notification::fake();
    notifyShop('remind-b', ['paid_until' => '2026-10-20']); // 6 days left
    notifyShop('remind-c', ['paid_until' => '2026-10-17', 'status' => 'suspended']);
    notifyShop('remind-d', ['paid_until' => null]);

    $this->artisan('subscriptions:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('warns again on the last day of the grace period', function () {
    Carbon::setTestNow('2026-10-14 09:00:00');
    Notification::fake();
    $grace = (int) config('plans.grace_days');
    notifyShop('remind-e', ['paid_until' => Carbon::now()->subDays($grace)->toDateString()]);

    $this->artisan('subscriptions:remind')->assertSuccessful();

    Notification::assertSentOnDemand(SubscriptionReminderNotification::class, fn (SubscriptionReminderNotification $n) => $n->daysLeft === -$grace);
});

it('writes the reminder in the owner language', function () {
    config(['plans.grace_days' => 7]);
    $notification = new SubscriptionReminderNotification('Duka Langu', 3, '17/10/2026');
    $notification->locale('sw');

    app()->setLocale('sw');
    $sms = $notification->toSms(new AnonymousNotifiable);
    app()->setLocale('en');

    expect($sms)->toContain('Duka Langu')->toContain('kifurushi')->toContain('17/10/2026');
    expect($notification->toSms(new AnonymousNotifiable))->toContain('subscription');
});

it('sends a receipt when a renewal is recorded, and not again for the same key', function () {
    Notification::fake();
    Carbon::setTestNow('2026-10-14 09:00:00');
    $tenant = notifyShop('receipt-a', ['paid_until' => '2026-10-20']);
    $plan = SubscriptionPlan::query()->where('code', 'medium')->firstOrFail();
    $plan->update(['monthly_price' => 7000000]);

    $data = ['months' => 1, 'method' => 'mpesa', 'paid_on' => '2026-10-14', 'idempotency_key' => 'renew-key-0001'];
    app(RenewSubscriptionAction::class)->handle($tenant, $data, null);
    app(RenewSubscriptionAction::class)->handle($tenant, $data, null);

    expect(TenantPayment::query()->count())->toBe(1);
    Notification::assertSentOnDemandTimes(PaymentReceivedNotification::class, 1);
    Notification::assertSentOnDemand(PaymentReceivedNotification::class, fn (PaymentReceivedNotification $n, array $c, AnonymousNotifiable $to) => $n->until === '20/11/2026' && $to->routes['mail'] === 'owner@receipt-a.test');
});

it('does not fail a renewal when the owner has no email or phone on file', function () {
    Notification::fake();
    $tenant = notifyShop('receipt-b', ['paid_until' => '2026-10-20'], null);
    SubscriptionPlan::query()->where('code', 'medium')->update(['monthly_price' => 7000000]);

    $payment = app(RenewSubscriptionAction::class)->handle($tenant, ['months' => 1, 'method' => 'cash', 'paid_on' => '2026-10-14', 'idempotency_key' => 'renew-key-0002'], null);

    expect($payment->exists)->toBeTrue();
});

it('alerts the platform team when a shop says it has paid', function () {
    Notification::fake();
    config(['notifications.admin_email' => 'team@odessa.test', 'notifications.admin_phone' => '0755 000 111']);
    $tenant = createTenant('alert-a', 'medium');
    $tenant->update(['paid_until' => '2026-12-31']);
    $user = createTenantUser($tenant, ['settings.manage']);

    $payload = ['idempotency_key' => 'notice-key-alert-1', 'method' => 'mpesa', 'months' => 1, 'amount' => '70000', 'reference' => 'ALERT123', 'paid_on' => '2026-10-11'];
    $this->actingAs($user)->withHeader('X-Tenant', 'alert-a')->post('/billing/notices', $payload)->assertRedirect();
    $this->actingAs($user)->withHeader('X-Tenant', 'alert-a')->post('/billing/notices', $payload)->assertRedirect();

    expect(PaymentNotice::query()->count())->toBe(1);
    Notification::assertSentOnDemandTimes(PaymentNoticeReceivedNotification::class, 1);
    Notification::assertSentOnDemand(PaymentNoticeReceivedNotification::class, fn ($n, $c, AnonymousNotifiable $to) => $to->routes['mail'] === 'team@odessa.test' && $n->reference === 'ALERT123');
});

it('sends no alert when no platform contact is configured', function () {
    Notification::fake();
    config(['notifications.admin_email' => null, 'notifications.admin_phone' => null]);
    $tenant = createTenant('alert-b', 'medium');
    $tenant->update(['paid_until' => '2026-12-31']);
    $user = createTenantUser($tenant, ['settings.manage']);

    $this->actingAs($user)->withHeader('X-Tenant', 'alert-b')->post('/billing/notices', ['idempotency_key' => 'notice-key-alert-2', 'method' => 'mpesa', 'months' => 1, 'amount' => '70000', 'reference' => 'ALERT456', 'paid_on' => '2026-10-11'])->assertRedirect();

    Notification::assertNothingSent();
});

it('saves the phone number from the profile in one format and rejects nonsense', function () {
    $tenant = createTenant('phone-a', 'medium');
    $user = createTenantUser($tenant, ['settings.manage']);
    $base = ['name' => $user->name, 'email' => $user->email, 'locale' => 'en'];

    $this->actingAs($user)->withHeader('X-Tenant', 'phone-a')->put(route('profile.update'), $base + ['phone' => '0712 345 678'])->assertSessionHasNoErrors();
    expect(User::query()->withoutGlobalScopes()->find($user->getKey())->phone)->toBe('255712345678');

    $this->actingAs($user)->withHeader('X-Tenant', 'phone-a')->put(route('profile.update'), $base + ['phone' => 'abc'])->assertSessionHasErrors('phone');
});
