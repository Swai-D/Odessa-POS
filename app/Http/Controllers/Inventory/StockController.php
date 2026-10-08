<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Http\Controllers\Controller;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        return view('stock.index', [
            'stocks' => ProductStock::query()
                ->with(['product.unit', 'warehouse'])
                ->whereHas('product')
                ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['product.name', 'product.sku']))
                ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'product_stocks.product_id'))
                ->orderBy('warehouse_id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
