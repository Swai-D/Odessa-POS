<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Integrations\Services\EscPosReceipt;
use App\Domain\Integrations\Services\IntegrationManager;
use App\Domain\Sales\Actions\RecordPaymentAction;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            'sale' => $sale->load(['items', 'payments.user', 'customer', 'user', 'warehouse', 'returns']),
            'format' => $request->query('format') === 'thermal' ? 'thermal' : 'a4',
            'canPay' => Gate::allows('update', $sale),
            'escpos' => app(IntegrationManager::class)->isActive('printer', 'escpos'),
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()->name ?? config('app.name')),
            'footer' => (string) (new TenantSettings)->get('receipt_footer', ''),
        ]);
    }

    /** Raw ESC/POS bytes (base64) for the browser to send to the thermal printer. */
    public function escpos(Sale $sale, IntegrationManager $integrations, EscPosReceipt $receipt): JsonResponse
    {
        Gate::authorize('view', $sale);

        $printer = $integrations->active('printer');
        abort_unless($printer !== null && $printer['driver'] === 'escpos', 404);

        $settings = new TenantSettings;
        $sale->load(['items', 'customer', 'user']);

        $bytes = $receipt->build(
            $sale,
            (string) $settings->get('business_name', app(TenantContext::class)->get()->name),
            (string) $settings->get('receipt_footer', ''),
            (int) ($printer['settings']['paper_width'] ?? 80),
            (string) ($printer['settings']['cut'] ?? '1') === '1',
        );

        return response()->json([
            'data' => base64_encode($bytes),
            'connection' => $printer['settings']['connection'] ?? 'serial',
            'copies' => max(1, (int) ($printer['settings']['copies'] ?? 1)),
        ]);
    }

    public function pdf(Sale $sale): Response
    {
        Gate::authorize('view', $sale);

        $sale->load(['items', 'payments', 'customer', 'user']);

        return Pdf::loadView('sales.receipt-pdf', [
            'sale' => $sale,
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()->name ?? config('app.name')),
            'footer' => (string) (new TenantSettings)->get('receipt_footer', ''),
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
