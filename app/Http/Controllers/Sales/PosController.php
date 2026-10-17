<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Integrations\Services\EscPosReceipt;
use App\Domain\Integrations\Services\IntegrationManager;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\HeldOrder;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentRequest;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\PromotionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\HoldOrderRequest;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index(): View
    {
        Gate::authorize('create', Sale::class);

        $warehouses = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name']);

        return view('pos.index', [
            'warehouses' => $warehouses,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->limit(40)->get(['id', 'name']),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(20)->get(['id', 'name', 'phone']),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'paymentMethods' => Payment::methods(),
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()->name ?? config('app.name')),
            'promotions' => app(PromotionService::class)->automatic()->map(fn ($promotion): array => $promotion->toEngine())->all(),
            'timezone' => app(PromotionService::class)->timezone(),
            'canCreateCustomer' => Gate::allows('create', Customer::class),
            'mobileMoney' => [
                'enabled' => app(IntegrationManager::class)->isActive('payments', 'azampay'),
                'providers' => PaymentRequest::PROVIDERS,
            ],
            'promotionsEnabled' => app(PromotionService::class)->enabled(),
            'scanner' => (new TenantSettings)->feature('barcode_scanner'),
            'drawer' => $this->drawer(),
        ]);
    }

    /**
     * The cash drawer opens through the ESC/POS printer, so the switch only does something when that printer is set up.
     *
     * @return array{enabled: bool, connection: string, data: string}
     */
    private function drawer(): array
    {
        $printer = app(IntegrationManager::class)->active('printer');
        $enabled = (new TenantSettings)->feature('cash_drawer') && $printer !== null && $printer['driver'] === 'escpos';

        return [
            'enabled' => $enabled,
            'connection' => (string) ($printer['settings']['connection'] ?? 'serial'),
            'data' => $enabled ? base64_encode(EscPosReceipt::DRAWER_PULSE) : '',
        ];
    }

    /** Product search for the till: by name, SKU or barcode, with stock for the selected warehouse. */
    public function products(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $warehouseId = $request->integer('warehouse');
        $term = trim((string) $request->query('q', ''));
        $normalizedTerm = mb_strtolower($term);
        $escapedTerm = addcslashes($normalizedTerm, '\\%_');
        $like = '%'.$escapedTerm.'%';
        $prefix = $escapedTerm.'%';

        $products = Product::query()
            ->with(['category:id,name', 'unit:id,short_name,allow_decimal'])
            ->withSum(['stocks as stock_quantity' => fn ($q) => $q->where('warehouse_id', $warehouseId)], 'quantity')
            ->withMin(['batches as next_expiry' => fn ($q) => $q->where('warehouse_id', $warehouseId)->where('quantity_remaining', '>', 0)->whereNotNull('expiry_date')], 'expiry_date')
            ->where('is_active', true)
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($term !== '', function ($q) use ($like, $normalizedTerm, $prefix): void {
                $q->where(function ($q) use ($like): void {
                    $q->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$like]);
                })->orderByRaw(
                    'CASE WHEN LOWER(barcode) = ? THEN 0 WHEN LOWER(sku) = ? THEN 1 WHEN LOWER(name) = ? THEN 2 WHEN LOWER(barcode) LIKE ? THEN 3 WHEN LOWER(sku) LIKE ? THEN 4 ELSE 5 END',
                    [$normalizedTerm, $normalizedTerm, $normalizedTerm, $prefix, $prefix],
                );
            })
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', array_map('intval', (array) $ids)))
            ->orderBy('name')
            ->limit($request->has('ids') ? 200 : 60)
            ->get();

        return response()->json(['data' => $products->map(fn (Product $product): array => $this->productPayload($product))->all()]);
    }

    /** @return array<string, mixed> */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->getKey(),
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'category' => $product->category?->name,
            'category_id' => $product->category_id,
            'price' => $product->selling_price,
            'tax_rate' => (float) $product->tax_rate,
            'tax_inclusive' => $product->tax_inclusive,
            'track_stock' => $product->track_stock,
            'stock' => $product->track_stock ? (float) ($product->stock_quantity ?? 0) : null,
            'allow_decimal' => (bool) ($product->is_weighed || $product->unit?->allow_decimal),
            'unit' => $product->unit?->short_name,
            // Date the oldest stock on the shelf goes off (products that track expiry only).
            'expiry' => $product->track_expiry && $product->getAttribute('next_expiry') ? substr((string) $product->getAttribute('next_expiry'), 0, 10) : null,
            'initials' => Str::upper(Str::substr($product->name, 0, 2)),
        ];
    }

    /** Everything the till needs to keep selling without a connection: products with stock, customers, categories. */
    public function snapshot(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $warehouseId = $request->integer('warehouse');

        $products = Product::query()
            ->with(['category:id,name', 'unit:id,short_name,allow_decimal'])
            ->withSum(['stocks as stock_quantity' => fn ($q) => $q->where('warehouse_id', $warehouseId)], 'quantity')
            ->withMin(['batches as next_expiry' => fn ($q) => $q->where('warehouse_id', $warehouseId)->where('quantity_remaining', '>', 0)->whereNotNull('expiry_date')], 'expiry_date')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit((int) config('pos.offline_max_products'))
            ->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'warehouse_id' => $warehouseId,
            'products' => $products->map(fn (Product $product): array => $this->productPayload($product))->all(),
            'promotions' => app(PromotionService::class)->automatic()->map(fn ($promotion): array => $promotion->toEngine())->all(),
            'timezone' => app(PromotionService::class)->timezone(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')
                ->limit((int) config('pos.offline_max_customers'))
                ->get(['id', 'name', 'phone'])->all(),
        ]);
    }

    /** A coupon code entered at the till: its rules, so the screen can show the discount before the sale. */
    public function coupon(Request $request, PromotionService $promotions): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        return response()->json(['data' => $promotions->findCoupon($data['code'], now())->toEngine()]);
    }

    /** A fresh CSRF token, so a till that was loaded long ago (or from the offline cache) can sync its queue. */
    public function session(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        return response()->json(['csrf' => csrf_token()]);
    }

    public function heldIndex(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $orders = HeldOrder::query()
            ->when(! Gate::allows('update', new Sale), fn ($q) => $q->where('user_id', $request->user()->getKey()))
            ->latest('id')
            ->limit(100)
            ->get();

        $customers = Customer::query()->whereIn('id', $orders->pluck('customer_id')->filter())->pluck('name', 'id');

        return response()->json([
            'data' => $orders->map(fn (HeldOrder $order): array => [
                'id' => $order->getKey(),
                'reference' => $order->reference,
                'customer' => $customers[$order->customer_id] ?? null,
                'items' => count($order->payload['items'] ?? []),
                'created_at' => $order->created_at?->diffForHumans(),
            ])->all(),
        ]);
    }

    public function hold(HoldOrderRequest $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $data = $request->validated();

        $order = HeldOrder::create([
            'user_id' => $request->user()->getKey(),
            'customer_id' => $data['customer_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'payload' => ['items' => $data['items'], 'discount' => $data['discount'] ?? null],
        ]);

        return response()->json(['id' => $order->getKey()], 201);
    }

    /** Returns the held cart and removes it, so a cart can only be resumed once. */
    public function resume(Request $request, HeldOrder $heldOrder): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $this->authorizeHeld($request, $heldOrder);

        $payload = [
            'customer_id' => $heldOrder->customer_id,
            'items' => $heldOrder->payload['items'] ?? [],
            'discount' => $heldOrder->payload['discount'] ?? null,
        ];
        $heldOrder->delete();

        return response()->json($payload);
    }

    public function discard(Request $request, HeldOrder $heldOrder): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $this->authorizeHeld($request, $heldOrder);

        $heldOrder->delete();

        return response()->json(['deleted' => true]);
    }

    /** Cashiers may only touch their own held carts; managers can handle everyone's. */
    private function authorizeHeld(Request $request, HeldOrder $heldOrder): void
    {
        abort_unless(
            $heldOrder->user_id === $request->user()->getKey() || Gate::allows('update', new Sale),
            403,
        );
    }
}
