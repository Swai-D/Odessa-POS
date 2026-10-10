<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Sales\Models\Payment;
use App\Domain\Settings\Services\PlatformPaymentReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformPaymentFilterRequest;
use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformPaymentController extends Controller
{
    public function index(PlatformPaymentFilterRequest $request, PlatformPaymentReport $report): View
    {
        $filters = $request->filters();
        $query = $report->query($filters);

        return view('platform.payments.index', [
            'payments' => (clone $query)->latest('paid_on')->latest('id')->paginate(25)->withQueryString(),
            'totals' => $report->totalsByCurrency($query),
            'filters' => $filters,
            'shops' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => TenantPayment::query()->whereHas('tenant')->distinct()->orderBy('currency')->pluck('currency'),
            'methods' => Payment::methods(),
        ]);
    }

    public function export(PlatformPaymentFilterRequest $request, PlatformPaymentReport $report): StreamedResponse
    {
        $filters = $request->filters();
        $rows = $report->query($filters)
            ->latest('paid_on')
            ->latest('id')
            ->get()
            ->map(static fn (TenantPayment $payment): array => [
                $payment->tenant->name,
                $payment->tenant->slug,
                $payment->paid_on->format('Y-m-d'),
                $payment->plan_name ?? $payment->plan ?? '',
                $payment->plan_price_amount === null ? Money::toMajor($payment->amount) : Money::toMajor($payment->plan_price_amount),
                Money::toMajor($payment->discount_amount),
                $payment->discount_reason ?? '',
                Money::toMajor($payment->amount),
                $payment->currency,
                __('pos.methods.'.$payment->method),
                $payment->reference ?? '',
                $payment->note ?? '',
                $payment->months,
                $payment->period_start->format('Y-m-d'),
                $payment->period_end->format('Y-m-d'),
            ])
            ->all();

        return Csv::download(
            sprintf('subscription-payments-%s-%s.csv', str_replace('-', '', $filters['from']), str_replace('-', '', $filters['to'])),
            [
                __('platform.name'), __('platform.slug'), __('platform.paid_on'), __('platform.payments.plan'),
                __('platform.list_price'), __('platform.discount'), __('platform.discount_reason'), __('platform.amount'),
                __('platform.payments.currency'), __('platform.method'), __('platform.reference'), __('platform.note'),
                __('platform.payments.months'), __('platform.period_start'), __('platform.period_end'),
            ],
            $rows,
        );
    }
}
