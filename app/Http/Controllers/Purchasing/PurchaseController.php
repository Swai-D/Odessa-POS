<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Purchasing\Actions\CreatePurchaseAction;
use App\Domain\Purchasing\Actions\RecordPurchasePaymentAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchasePaymentRequest;
use App\Http\Requests\PurchaseRequest;
use App\Support\Money;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Purchase::class);

        return view('purchases.index', [
            'purchases' => Purchase::query()
                ->with('supplier:id,name')
                ->when($request->query('status') === 'unpaid', fn ($q) => $q->where('balance_due', '>', 0))
                ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['number', 'reference', 'supplier.name']))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'status' => $request->query('status'),
            'canCreate' => Gate::allows('create', Purchase::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Purchase::class);

        return view('purchases.create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            // Only the products of a form that failed validation are preloaded; the rest is searched on demand.
            'restoredProducts' => Product::query()
                ->whereKey(collect(old('items', []))->pluck('product_id')->filter()->all())
                ->get(['id', 'name', 'sku'])
                ->mapWithKeys(fn (Product $p): array => [$p->getKey() => $p->name.' ('.$p->sku.')'])
                ->all(),
            'methods' => Payment::methods(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(PurchaseRequest $request, CreatePurchaseAction $action): RedirectResponse
    {
        Gate::authorize('create', Purchase::class);

        $data = $request->validated();

        $payload = [
            'idempotency_key' => $data['idempotency_key'],
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'update_cost' => (bool) ($data['update_cost'] ?? false),
            'items' => array_map(fn (array $row): array => [
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'unit_cost' => Money::toMinor($row['unit_cost']),
            ], array_values($data['items'])),
            'payments' => [],
        ];

        $amount = Money::toMinor($data['payment_amount'] ?? 0);
        if ($amount > 0) {
            if (empty($data['payment_method'])) {
                return back()->withInput()->withErrors(['payment_method' => __('purchases.errors.method_required')]);
            }
            $payload['payments'][] = [
                'method' => $data['payment_method'],
                'amount' => $amount,
                'reference' => $data['payment_reference'] ?? null,
            ];
        }

        ['purchase' => $purchase] = $action->handle($payload, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', __('app.saved'));
    }

    public function show(Purchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        return view('purchases.show', [
            'purchase' => $purchase->load(['items', 'payments.user', 'supplier', 'warehouse', 'user']),
            'canPay' => Gate::allows('update', $purchase),
            'methods' => Payment::methods(),
        ]);
    }

    public function storePayment(PurchasePaymentRequest $request, Purchase $purchase, RecordPurchasePaymentAction $action): RedirectResponse
    {
        Gate::authorize('update', $purchase);

        $data = $request->validated();
        $data['amount'] = Money::toMinor($data['amount']);

        $action->handle($purchase, $data, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', __('app.saved'));
    }
}
