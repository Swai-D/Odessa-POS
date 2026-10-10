<?php

namespace App\Domain\Settings\Services;

use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PlatformOverview
{
    /**
     * @return array{
     *     shops: int,
     *     active: int,
     *     trial: int,
     *     suspended: int,
     *     revenueByCurrency: list<array{currency: string, amount: int}>,
     *     recentPayments: Collection<int, TenantPayment>,
     *     recentShops: Collection<int, Tenant>,
     *     renewals: Collection<int, Tenant>,
     *     warnDays: int
     * }
     */
    public function summary(): array
    {
        $warnDays = max(0, (int) config('plans.warn_days'));
        $today = Carbon::today();
        $renewalCutoff = $today->copy()->addDays($warnDays)->toDateString();

        $revenueByCurrency = TenantPayment::query()
            ->whereBetween('paid_on', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()])
            ->selectRaw('currency, SUM(amount) as amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(static fn (TenantPayment $payment): array => [
                'currency' => (string) $payment->getAttribute('currency'),
                'amount' => (int) $payment->getAttribute('amount'),
            ])
            ->all();

        return [
            'shops' => Tenant::query()->count(),
            'active' => Tenant::query()->where('status', 'active')->count(),
            'trial' => Tenant::query()->where('status', 'trial')->count(),
            'suspended' => Tenant::query()->where('status', 'suspended')->count(),
            'revenueByCurrency' => $revenueByCurrency,
            'recentPayments' => TenantPayment::query()
                ->whereHas('tenant')
                ->with('tenant')
                ->latest('paid_on')
                ->latest('id')
                ->limit(5)
                ->get(),
            'recentShops' => Tenant::query()->latest()->limit(5)->get(),
            'renewals' => Tenant::query()
                ->where('status', '!=', 'suspended')
                ->where(static function (Builder $query) use ($renewalCutoff): void {
                    $query->whereDate('paid_until', '<=', $renewalCutoff)
                        ->orWhere(static function (Builder $query) use ($renewalCutoff): void {
                            $query->where('status', 'trial')
                                ->whereNotNull('trial_ends_at')
                                ->whereDate('trial_ends_at', '<=', $renewalCutoff);
                        });
                })
                ->orderByRaw('COALESCE(paid_until, trial_ends_at) asc')
                ->limit(8)
                ->get(),
            'warnDays' => $warnDays,
        ];
    }
}
