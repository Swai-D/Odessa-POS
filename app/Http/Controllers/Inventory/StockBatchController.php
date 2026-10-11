<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Inventory\Models\StockMovement;
use App\Http\Controllers\Controller;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read-only view of the stock still on the shelf, batch by batch, with the cost each batch was bought at. */
class StockBatchController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        return view('stock.batches', [
            'batches' => StockBatch::query()
                ->with(['product.unit', 'warehouse'])
                ->where('quantity_remaining', '>', 0)
                ->whereHas('product', fn ($q) => $q->where('track_stock', true))
                ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['product.name', 'product.sku']))
                ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'stock_batches.product_id'))
                ->orderBy('warehouse_id')
                ->orderBy('received_at')
                ->orderBy('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
