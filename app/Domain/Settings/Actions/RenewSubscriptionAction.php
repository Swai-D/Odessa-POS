<?php

namespace App\Domain\Settings\Actions;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a subscription payment and moves the shop's paid-until date forward.
 *
 * The new period starts from the current paid-until date, or from the payment day when the shop has lapsed
 * (a late payer does not get the gap for free). Sending the same idempotency key again returns the first
 * payment and changes nothing, so a double click cannot extend the subscription twice.
 */
class RenewSubscriptionAction
{
    /** @param  array{months: int, discount_amount?: float|int|string|null, discount_reason?: string|null, method: string, paid_on: string, reference?: string|null, note?: string|null, idempotency_key: string}  $data */
    public function handle(Tenant $tenant, array $data, ?User $by): TenantPayment
    {
        return DB::transaction(function () use ($tenant, $data, $by): TenantPayment {
            $existing = TenantPayment::query()->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing !== null) {
                return $existing;
            }

            // Lock the shop row so two admins renewing at once cannot both start from the same date.
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            $planCode = (string) config("plans.aliases.{$locked->plan}", $locked->plan);
            $plan = SubscriptionPlan::query()->where('code', $planCode)->first();
            $price = $plan?->getAttribute((int) $data['months'] === 12 ? 'annual_price' : 'monthly_price');
            if (! is_int($price) || $price < 1) {
                throw ValidationException::withMessages(['months' => __('platform.billing_price_missing')]);
            }

            $discount = Money::toMinor($data['discount_amount'] ?? 0);
            if ($discount > $price) {
                throw ValidationException::withMessages(['discount_amount' => __('platform.discount_exceeds_price')]);
            }

            $paidOn = Carbon::parse($data['paid_on'])->startOfDay();
            $start = $locked->paid_until !== null && $locked->paid_until->greaterThan($paidOn)
                ? $locked->paid_until->copy()->startOfDay()
                : $paidOn->copy();
            $end = $start->copy()->addMonthsNoOverflow((int) $data['months']);

            $payment = TenantPayment::query()->create([
                'tenant_id' => $locked->getKey(),
                'user_id' => $by?->getKey(),
                'idempotency_key' => $data['idempotency_key'],
                'plan' => $planCode,
                'plan_name' => $plan->name,
                'plan_price_amount' => $price,
                'discount_amount' => $discount,
                'discount_reason' => $discount > 0 ? $data['discount_reason'] : null,
                'amount' => $price - $discount,
                'currency' => 'TZS',
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'months' => (int) $data['months'],
                'paid_on' => $paidOn,
                'period_start' => $start,
                'period_end' => $end,
            ]);

            // A paying trial shop becomes a normal active one; a suspended shop stays suspended (admin's call).
            $locked->update([
                'paid_until' => $end,
                'status' => $locked->status === 'trial' ? 'active' : $locked->status,
            ]);

            return $payment;
        });
    }
}
