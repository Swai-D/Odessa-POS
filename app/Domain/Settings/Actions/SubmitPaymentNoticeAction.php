<?php

namespace App\Domain\Settings\Actions;

use App\Models\PaymentNotice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Records "we have paid" from a shop. Sending the same idempotency key again returns the first notice, so a double
 * click sends one message; a shop cannot have more than a handful waiting, and cannot send the same transaction twice.
 */
class SubmitPaymentNoticeAction
{
    /** @param  array{method: string, months: int|string, amount: float|int|string, reference: string, paid_on: string, note?: string|null, idempotency_key: string}  $data */
    public function handle(Tenant $tenant, array $data, ?User $by): PaymentNotice
    {
        $existing = PaymentNotice::query()->where('idempotency_key', $data['idempotency_key'])->where('tenant_id', $tenant->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        $reference = trim($data['reference']);

        if (PaymentNotice::query()->where('tenant_id', $tenant->getKey())->where('reference', $reference)->exists()) {
            throw ValidationException::withMessages(['reference' => __('billing.notice.duplicate')]);
        }

        $pending = PaymentNotice::query()->where('tenant_id', $tenant->getKey())->where('status', PaymentNotice::PENDING)->count();

        if ($pending >= PaymentNotice::MAX_PENDING) {
            throw ValidationException::withMessages(['reference' => __('billing.notice.too_many')]);
        }

        return PaymentNotice::query()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $by?->getKey(),
            'idempotency_key' => $data['idempotency_key'],
            'method' => $data['method'],
            'amount' => Money::toMinor($data['amount']),
            'currency' => 'TZS',
            'months' => (int) $data['months'],
            'reference' => $reference,
            'paid_on' => $data['paid_on'],
            'note' => $data['note'] ?? null,
            'status' => PaymentNotice::PENDING,
        ]);
    }
}
