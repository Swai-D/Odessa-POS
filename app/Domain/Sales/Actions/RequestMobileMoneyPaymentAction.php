<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\PaymentRequest;
use App\Domain\Sales\Services\AzamPayClient;
use App\Domain\Sales\Services\AzamPayException;
use App\Models\User;
use App\Support\Money;
use App\Support\Sms\PhoneNumber;
use App\Support\TenantSettings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sends a payment prompt to a customer's phone through the shop's AzamPay app. The request is saved first (so a callback
 * can always find it), then AzamPay is asked. The same idempotency key returns the first request, so a double click or a
 * retry never prompts the customer twice. The sale itself is made later by the normal checkout, once this has succeeded.
 */
class RequestMobileMoneyPaymentAction
{
    /** @param  array{idempotency_key: string, provider: string, phone: string, amount: int}  $data  amount in minor units */
    public function handle(array $data, ?User $user): PaymentRequest
    {
        $existing = PaymentRequest::query()->where('idempotency_key', $data['idempotency_key'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $client = AzamPayClient::forCurrentTenant();

        if ($client === null) {
            throw ValidationException::withMessages(['provider' => __('mobile_money.errors.not_configured')]);
        }

        $phone = PhoneNumber::normalize($data['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('notifications.phone_invalid')]);
        }

        if ($data['amount'] < 100 || $data['amount'] % 100 !== 0 || $data['amount'] > (int) config('azampay.max_amount') * 100) {
            throw ValidationException::withMessages(['amount' => __('mobile_money.errors.amount', ['max' => Money::format((int) config('azampay.max_amount') * 100)])]);
        }

        $currency = (string) (new TenantSettings)->get('currency', config('pos.default_currency'));

        $request = PaymentRequest::query()->create([
            'user_id' => $user?->getKey(),
            'idempotency_key' => $data['idempotency_key'],
            'reference' => 'OD'.strtoupper((string) Str::ulid()),
            'driver' => 'azampay',
            'provider' => $data['provider'],
            'phone' => $phone,
            'amount' => $data['amount'],
            'currency' => $currency,
            'status' => PaymentRequest::PENDING,
            'expires_at' => now()->addMinutes((int) config('azampay.request_ttl_minutes')),
        ]);

        try {
            $result = $client->checkout($phone, intdiv($data['amount'], 100), $currency, $request->reference, $data['provider']);
        } catch (AzamPayException $e) {
            report($e);
            // The prompt may or may not have left; a late successful callback can still mark this request paid.
            $request->update(['status' => PaymentRequest::FAILED, 'failure_reason' => 'unreachable', 'completed_at' => now()]);

            return $request;
        }

        if (! $result['success']) {
            $request->update(['status' => PaymentRequest::FAILED, 'failure_reason' => mb_substr((string) ($result['message'] ?? 'rejected'), 0, 250), 'completed_at' => now()]);

            return $request;
        }

        $request->update(['provider_reference' => $result['transactionId']]);

        return $request;
    }
}
