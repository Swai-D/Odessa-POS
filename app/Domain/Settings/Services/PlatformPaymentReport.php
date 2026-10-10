<?php

namespace App\Domain\Settings\Services;

use App\Models\TenantPayment;
use Illuminate\Database\Eloquent\Builder;

class PlatformPaymentReport
{
    /**
     * @param  array{from: string, to: string, tenant_id: string, currency: string, method: string}  $filters
     * @return Builder<TenantPayment>
     */
    public function query(array $filters): Builder
    {
        return TenantPayment::query()
            ->whereHas('tenant')
            ->with('tenant')
            ->whereBetween('paid_on', [$filters['from'], $filters['to']])
            ->when($filters['tenant_id'] !== '', fn (Builder $query) => $query->where('tenant_id', $filters['tenant_id']))
            ->when($filters['currency'] !== '', fn (Builder $query) => $query->where('currency', $filters['currency']))
            ->when($filters['method'] !== '', fn (Builder $query) => $query->where('method', $filters['method']));
    }

    /**
     * @param  Builder<TenantPayment>  $query
     * @return list<array{currency: string, amount: int}>
     */
    public function totalsByCurrency(Builder $query): array
    {
        return (clone $query)
            ->reorder()
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get()
            ->map(static fn (TenantPayment $payment): array => [
                'currency' => (string) $payment->getAttribute('currency'),
                'amount' => (int) $payment->getAttribute('total'),
            ])
            ->all();
    }
}
