<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockMovement::class);

        return view('stock.adjustments', [
            'movements' => StockMovement::query()
                ->with(['product', 'warehouse', 'user'])
                ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['product.name', 'product.sku']))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'selectedProduct' => $this->productChoice($request->old('product_id')),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'canManage' => Gate::allows('create', StockMovement::class),
        ]);
    }

    public function store(StockAdjustmentRequest $request, StockService $stock): RedirectResponse
    {
        Gate::authorize('create', StockMovement::class);

        $data = $request->validated();
        $quantity = (float) $data['quantity'];

        try {
            $stock->move(
                Product::query()->findOrFail($data['product_id']),
                Warehouse::query()->findOrFail($data['warehouse_id']),
                $data['direction'] === 'out' ? -$quantity : $quantity,
                $data['direction'] === 'out' ? StockMovement::ADJUSTMENT_OUT : StockMovement::ADJUSTMENT_IN,
                $data['reason'],
                $request->user(),
            );
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('stock-adjustments.index')->with('status', __('app.saved'));
    }

    /** @return array{id: int, label: string}|null the product to pre-fill after a failed submit */
    private function productChoice(mixed $id): ?array
    {
        $product = $id ? Product::query()->find($id) : null;

        return $product ? ['id' => (int) $product->getKey(), 'label' => $product->name.' ('.$product->sku.')'] : null;
    }
}
