<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\SaveProductAction;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Support\Money;
use App\Support\Search;
use App\Support\Sort;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category', 'brand', 'unit', 'imageFile'])
            ->withSum('stocks', 'quantity')
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->integer('brand'), fn ($q, $id) => $q->where('brand_id', $id))
            ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['name', 'sku', 'barcode']));

        [$sort, $dir] = Sort::apply($products, $request, ['sku' => 'sku', 'name' => 'name', 'price' => 'selling_price'], 'name');
        $products = $products->paginate(25)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'sort' => $sort,
            'dir' => $dir,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', Product::class),
        ]);
    }

    /** Search-as-you-type feed for product pickers: name, SKU or barcode; optionally only stock-tracked products. */
    public function lookup(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::query()->where('is_active', true)
            ->when($request->boolean('tracked'), fn ($q) => $q->where('track_stock', true));

        return response()->json([
            'data' => Search::apply($query, $request->query('q'), ['name', 'sku', 'barcode'])
                ->orderBy('name')->limit(20)->get(['id', 'name', 'sku', 'barcode', 'track_stock', 'track_batch', 'track_expiry', 'cost_price', 'selling_price'])
                ->map(fn (Product $p): array => $this->labelItem($p) + [
                    'label' => $p->name.' ('.$p->sku.')', 'cost' => Money::toMajor($p->cost_price), 'price' => Money::toMajor($p->selling_price),
                    'track_batch' => (bool) $p->track_batch, 'track_expiry' => (bool) $p->track_expiry,
                ])->all(),
        ]);
    }

    /** Barcode label printing: pick products, set copies and a label size, print. `?product[]=id` preloads rows. */
    public function labels(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $ids = collect((array) $request->query('product', []))->map(fn ($id) => (int) $id)->filter()->unique()->take(50)->all();
        $preset = $ids === [] ? collect() : Product::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'sku', 'barcode', 'track_stock', 'selling_price']);

        return view('products.labels', [
            'initial' => $preset->map(fn (Product $p): array => $this->labelItem($p))->all(),
            'business' => (string) (new TenantSettings)->get('business_name', app(TenantContext::class)->get()->name ?? config('app.name')),
        ]);
    }

    /** @return array<string, mixed> */
    private function labelItem(Product $product): array
    {
        return [
            'id' => $product->getKey(),
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'price_text' => Money::format($product->selling_price),
            'stock' => $product->track_stock ? $product->totalQuantity() : null,
        ];
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('products.form', $this->formData(new Product([
            'type' => Product::TYPE_STANDARD,
            'tax_inclusive' => true,
            'track_stock' => true,
            'is_active' => true,
            'tax_rate' => 0,
            'cost_price' => 0,
            'selling_price' => 0,
        ])));
    }

    public function store(ProductRequest $request, SaveProductAction $action): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $action->handle($request->validated(), null, $request->user());

        return redirect()->route('products.index')->with('status', __('app.saved'));
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        return view('products.form', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product, SaveProductAction $action): RedirectResponse
    {
        Gate::authorize('update', $product);

        $action->handle($request->validated(), $product, $request->user());

        return redirect()->route('products.index')->with('status', __('app.saved'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index')->with('status', __('app.deleted'));
    }

    /** @return array<string, mixed> */
    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'units' => Unit::query()->orderBy('name')->pluck('name', 'id'),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'settings' => new TenantSettings,
        ];
    }
}
