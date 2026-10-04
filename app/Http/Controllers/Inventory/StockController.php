<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Inventory\Models\StockMovement;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        return view('stock.index', [
            'stocks' => ProductStock::query()
                ->with(['product.unit', 'warehouse'])
                ->whereHas('product')
                ->get()
                ->sortBy(fn (ProductStock $s) => $s->product->name),
        ]);
    }
}
