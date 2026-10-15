<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Actions\ConvertQuotationAction;
use App\Domain\Sales\Actions\CreateQuotationAction;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Quotation;
use App\Domain\Sales\Services\SaleCalculator;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertQuotationRequest;
use App\Http\Requests\QuotationRequest;
use App\Support\Money;
use App\Support\Search;
use App\Support\Sort;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);

        $quotations = Quotation::query()
            ->with('customer:id,name')
            ->when(in_array($request->query('status'), [Quotation::DRAFT, Quotation::SENT, Quotation::ACCEPTED, Quotation::DECLINED, Quotation::CONVERTED], true), fn ($q) => $q->where('status', $request->query('status')))
            ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['number', 'customer_name', 'customer.name']));

        [$sort, $dir] = Sort::apply($quotations, $request, ['number' => 'number', 'date' => 'quoted_at', 'total' => 'total'], 'date', 'desc');

        return view('quotations.index', [
            'quotations' => $quotations->paginate(25)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'status' => $request->query('status'),
            'canCreate' => Gate::allows('create', Quotation::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Quotation::class);

        return view('quotations.create', [
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'restoredProducts' => Product::query()
                ->whereKey(collect(old('items', []))->pluck('product_id')->filter()->all())
                ->get(['id', 'name', 'sku'])
                ->mapWithKeys(fn (Product $p): array => [$p->getKey() => $p->name.' ('.$p->sku.')'])
                ->all(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(QuotationRequest $request, CreateQuotationAction $action): RedirectResponse
    {
        Gate::authorize('create', Quotation::class);

        $quotation = $action->handle($request->validated(), $request->user());

        return redirect()->route('quotations.show', $quotation)->with('status', __('app.saved'));
    }

    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        $quotation->load(['items', 'customer', 'user', 'sale']);
        $current = $this->currentTotal($quotation);

        return view('quotations.show', [
            'quotation' => $quotation,
            'canManage' => Gate::allows('update', $quotation),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'methods' => Payment::methods(),
            'priceChanged' => $current !== null && $current !== $quotation->total && $quotation->canConvert(),
            'currentTotal' => $current,
        ]);
    }

    public function pdf(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $quotation->load(['items', 'customer', 'user']);

        return Pdf::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()->name ?? config('app.name')),
        ])->setPaper('a4')->stream($quotation->number.'.pdf');
    }

    public function status(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('update', $quotation);

        $data = $request->validate(['status' => ['required', Rule::in(Quotation::manualStatuses())]]);

        // A converted quotation is closed; it already became a sale.
        abort_if($quotation->status === Quotation::CONVERTED, 422);

        $quotation->update(['status' => $data['status']]);

        return redirect()->route('quotations.show', $quotation)->with('status', __('app.saved'));
    }

    public function convert(ConvertQuotationRequest $request, Quotation $quotation, ConvertQuotationAction $action): RedirectResponse
    {
        Gate::authorize('update', $quotation);

        $data = $request->validated();
        $payments = [];
        $amount = Money::toMinor($data['payment_amount'] ?? 0);

        if ($amount > 0) {
            if (empty($data['payment_method'])) {
                return back()->withInput()->withErrors(['payment_method' => __('purchases.errors.method_required')]);
            }
            $payments[] = ['method' => $data['payment_method'], 'amount' => $amount, 'reference' => $data['payment_reference'] ?? null];
        }

        $sale = $action->handle($quotation, ['warehouse_id' => $data['warehouse_id'], 'payments' => $payments], $request->user());

        return redirect()->route('sales.show', $sale)->with('status', __('quotations.converted'));
    }

    /** What the same lines would cost at today's prices, in minor units; null when a product is gone. */
    private function currentTotal(Quotation $quotation): ?int
    {
        if ($quotation->status === Quotation::CONVERTED) {
            return null;
        }

        $products = Product::query()->whereIn('id', $quotation->items->pluck('product_id')->filter()->all())->get()->keyBy('id');

        if ($products->count() !== $quotation->items->count()) {
            return null;
        }

        $lines = $quotation->items->map(fn ($item): array => [
            'quantity' => (float) $item->quantity,
            'unit_price' => (int) $products[$item->product_id]->selling_price,
            'tax_rate' => (float) $products[$item->product_id]->tax_rate,
            'tax_inclusive' => (bool) $products[$item->product_id]->tax_inclusive,
        ])->values()->all();

        return (new SaleCalculator)->calculate($lines, $quotation->discount_total > 0 ? ['type' => 'fixed', 'value' => $quotation->discount_total] : null)['total'];
    }
}
