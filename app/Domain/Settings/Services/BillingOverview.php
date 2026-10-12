<?php

namespace App\Domain\Settings\Services;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Support\Plans;
use App\Support\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Everything the shop's Billing page shows: where the subscription stands, the plans, how to pay and what was paid.
 * Read-only; renewals are recorded by the platform team once a payment is confirmed.
 */
class BillingOverview
{
    /**
     * @return array{
     *     tenant: Tenant,
     *     plan: SubscriptionPlan|null,
     *     plans: Collection<int, SubscriptionPlan>,
     *     trial: bool,
     *     trial_plan_name: string,
     *     state: string,
     *     deadline: string|null,
     *     deadline_at: string|null,
     *     grace_ends: string|null,
     *     days_left: int|null,
     *     elapsed_percent: int,
     *     payments: Collection<int, TenantPayment>,
     *     methods: list<array{key: string, label: string, network: string, ussd: string, number: string}>,
     *     bank: array{name: string, account: string, branch: string|null}|null,
     *     account_name: string,
     *     contact: array{phone: string|null, email: string|null, whatsapp: string|null},
     * }
     */
    public function handle(Tenant $tenant): array
    {
        $subscription = Subscription::current();
        $deadline = $subscription->deadline();
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
        $payments = TenantPayment::query()->where('tenant_id', $tenant->getKey())->latest('paid_on')->latest('id')->limit(50)->get();

        return [
            'tenant' => $tenant,
            'plan' => SubscriptionPlan::query()->where('code', Plans::current()->key())->first(),
            'plans' => $plans,
            'trial' => $tenant->status === 'trial',
            'trial_plan_name' => (string) SubscriptionPlan::query()->where('code', config('plans.trial_plan'))->value('name'),
            'state' => $subscription->state(),
            'deadline' => $deadline?->format('Y-m-d'),
            'deadline_at' => $deadline?->copy()->endOfDay()->toIso8601String(),
            'grace_ends' => $subscription->graceEndsAt()?->format('Y-m-d'),
            'days_left' => $subscription->daysLeft(),
            'elapsed_percent' => $this->elapsedPercent($tenant, $payments->sortByDesc('period_end')->first(), $deadline),
            'payments' => $payments,
            'methods' => $this->methods(),
            'bank' => $this->bank(),
            'account_name' => (string) config('billing.account_name'),
            'contact' => [
                'phone' => $this->filled(config('plans.contact.phone')),
                'email' => $this->filled(config('plans.contact.email')),
                'whatsapp' => $this->filled(config('billing.whatsapp')),
            ],
        ];
    }

    /** How much of the current paid period (or trial) has gone, 0 to 100. */
    private function elapsedPercent(Tenant $tenant, ?TenantPayment $last, ?CarbonInterface $deadline): int
    {
        if ($deadline === null) {
            return 0;
        }

        $start = $tenant->status === 'trial' || $last === null ? $tenant->created_at : $last->period_start;
        $total = $start?->copy()->startOfDay()->diffInSeconds($deadline->copy()->endOfDay(), false) ?? 0;

        if ($start === null || $total <= 0) {
            return 100;
        }

        $done = $start->copy()->startOfDay()->diffInSeconds(Date::now(), false);

        return (int) max(0, min(100, round($done / $total * 100)));
    }

    /** @return list<array{key: string, label: string, network: string, ussd: string, number: string}> */
    private function methods(): array
    {
        $methods = [];

        foreach ((array) config('billing.mobile') as $key => $method) {
            $number = $this->filled($method['number'] ?? null);

            if ($number !== null) {
                $methods[] = ['key' => (string) $key, 'label' => (string) $method['label'], 'network' => (string) $method['network'], 'ussd' => (string) $method['ussd'], 'number' => $number];
            }
        }

        return $methods;
    }

    /** @return array{name: string, account: string, branch: string|null}|null */
    private function bank(): ?array
    {
        $account = $this->filled(config('billing.bank.account'));

        return $account === null ? null : [
            'name' => (string) $this->filled(config('billing.bank.name')),
            'account' => $account,
            'branch' => $this->filled(config('billing.bank.branch')),
        ];
    }

    private function filled(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
