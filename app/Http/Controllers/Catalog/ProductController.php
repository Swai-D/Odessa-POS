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
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->with(['category', 'brand', 'unit'])
            ->withSum('stocks', 'quantity')
            ->when($request->integer('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->integer('brand'), fn ($q, $id) => $q->where('brand_id', $id))
            ->orderBy('name')
            ->get();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('create', Product::class),
        ]);
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
