<?php

namespace App\Domain\Settings\Services;

use App\Models\PaymentNotice;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Notifications\DomainVerifiedNotification;
use App\Notifications\ExpiryNotification;
use App\Notifications\LowStockNotification;
use App\Notifications\PaymentNoticeReceivedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ShopNotification;
use App\Notifications\SubscriptionReminderNotification;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the shop owner (or the platform team) an email and an SMS. A message that cannot be sent is reported
 * and forgotten: a notification must never make a payment, a renewal or a sign-up fail.
 */
class ShopNotifier
{
    public function __construct(private readonly TenantOwnerService $owners) {}

    public function subscriptionReminder(Tenant $tenant, int $daysLeft, CarbonInterface $deadline): void
    {
        $this->toOwner($tenant, fn (): ShopNotification => new SubscriptionReminderNotification($tenant->name, $daysLeft, $deadline->format('d/m/Y')));
    }

    public function paymentReceived(TenantPayment $payment): void
    {
        $tenant = Tenant::query()->find($payment->tenant_id);

        if ($tenant === null) {
            return;
        }

        $this->toOwner($tenant, fn (): ShopNotification => new PaymentReceivedNotification(
            $tenant->name,
            Money::format((int) $payment->amount, (string) $payment->currency),
            $payment->period_end->format('d/m/Y'),
        ));
    }

    /** @param  array<int, string>  $names  The first few product names, for the message. */
    public function lowStock(Tenant $tenant, int $count, array $names): void
    {
        $this->toOwner($tenant, fn (): ShopNotification => new LowStockNotification($tenant->name, $count, implode(', ', $names)));
    }

    /** @param  array<int, string>  $names  The first few product names, for the message. */
    public function expiry(Tenant $tenant, int $count, int $expired, array $names): void
    {
        $this->toOwner($tenant, fn (): ShopNotification => new ExpiryNotification($tenant->name, $count, $expired, implode(', ', $names)));
    }

    public function paymentNoticeReceived(Tenant $tenant, PaymentNotice $notice): void
    {
        $email = config('notifications.admin_email');
        $phone = config('notifications.admin_phone');

        if (! is_string($email) && ! is_string($phone)) {
            return;
        }

        $this->send($email, $phone, 'en', new PaymentNoticeReceivedNotification(
            $tenant->name,
            Money::format((int) $notice->amount, (string) $notice->currency),
            (string) $notice->reference,
        ));
    }

    public function domainVerified(Tenant $tenant, string $domain): void
    {
        $email = config('notifications.admin_email');
        $phone = config('notifications.admin_phone');

        if (! is_string($email) && ! is_string($phone)) {
            return;
        }

        $this->send($email, $phone, 'en', new DomainVerifiedNotification($tenant->name, $domain));
    }

    /** @param  callable(): ShopNotification  $make */
    private function toOwner(Tenant $tenant, callable $make): void
    {
        try {
            $owner = $this->owners->find($tenant);

            if ($owner === null) {
                return;
            }

            $locale = in_array($owner->locale, ['en', 'sw'], true) ? $owner->locale : 'en';
            $this->send($owner->email, $owner->phone, $locale, $make());
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function send(mixed $email, mixed $phone, string $locale, ShopNotification $notification): void
    {
        try {
            $target = new AnonymousNotifiable;

            if (is_string($email) && $email !== '') {
                $target->route('mail', $email);
            }

            if (is_string($phone) && $phone !== '') {
                $target->route('sms', $phone);
            }

            Notification::send($target, $notification->locale($locale));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
