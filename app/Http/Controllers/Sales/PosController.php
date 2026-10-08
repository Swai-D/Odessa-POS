<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\HeldOrder;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\HoldOrderRequest;
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
            'canCreateCustomer' => Gate::allows('create', Customer::class),
        ]);
    }

    /** Product search for the till: by name, SKU or barcode, with stock for the selected warehouse. */
    public function products(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $warehouseId = $request->integer('warehouse');
        $term = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($term, '\\%_').'%';

        $products = Product::query()
            ->with(['category:id,name', 'unit:id,short_name,allow_decimal'])
            ->withSum(['stocks as stock_quantity' => fn ($q) => $q->where('warehouse_id', $warehouseId)], 'quantity')
            ->where('is_active', true)
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($term !== '', fn ($q) => $q->where(function ($q) use ($like, $term): void {
                $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', $term);
            }))
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', array_map('intval', (array) $ids)))
            ->orderBy('name')
            ->limit($request->has('ids') ? 200 : 60)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $product): array => [
                'id' => $product->getKey(),
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category' => $product->category?->name,
                'price' => $product->selling_price,
                'tax_rate' => (float) $product->tax_rate,
                'tax_inclusive' => $product->tax_inclusive,
                'track_stock' => $product->track_stock,
                'stock' => $product->track_stock ? (float) ($product->stock_quantity ?? 0) : null,
                'allow_decimal' => (bool) ($product->is_weighed || $product->unit?->allow_decimal),
                'unit' => $product->unit?->short_name,
                'initials' => Str::upper(Str::substr($product->name, 0, 2)),
            ])->all(),
        ]);
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
