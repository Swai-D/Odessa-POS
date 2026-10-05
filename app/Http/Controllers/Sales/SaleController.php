<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\RecordPaymentAction;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Support\Money;
use App\Support\TenantSettings;
use App\Support\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->with(['customer:id,name', 'user:id,name'])
            ->when($request->query('status') === 'unpaid', fn ($q) => $q->where('balance_due', '>', 0))
            ->latest('id')
            ->limit(500)
            ->get();

        return view('sales.index', [
            'sales' => $sales,
            'status' => $request->query('status'),
        ]);
    }

    public function show(Request $request, Sale $sale): View
    {
        Gate::authorize('view', $sale);

        return view('sales.show', [
            'sale' => $sale->load(['items', 'payments.user', 'customer', 'user', 'warehouse']),
            'format' => $request->query('format') === 'thermal' ? 'thermal' : 'a4',
            'canPay' => Gate::allows('update', $sale),
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()?->name ?? config('app.name')),
        ]);
    }

    public function pdf(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        $sale->load(['items', 'payments', 'customer', 'user']);

        return Pdf::loadView('sales.receipt-pdf', [
            'sale' => $sale,
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()?->name ?? config('app.name')),
        ])->setPaper('a4')->stream($sale->number.'.pdf');
    }

    public function storePayment(PaymentRequest $request, Sale $sale, RecordPaymentAction $action): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $data = $request->validated();
        $data['amount'] = Money::toMinor($data['amount']);

        $action->handle($sale, $data, $request->user());

        return redirect()->route('sales.show', $sale)->with('status', __('app.saved'));
    }
}
