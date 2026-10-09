<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Actions\CreateStockTransferAction;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransferRequest;
use App\Support\Search;
use App\Support\Sort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockTransfer::class);

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'user:id,name'])
            ->withCount('items')
            ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['number', 'note', 'fromWarehouse.name', 'toWarehouse.name']));

        [$sort, $dir] = Sort::apply($transfers, $request, ['number' => 'number', 'date' => 'transferred_at'], 'date', 'desc');

        return view('stock.transfers.index', [
            'transfers' => $transfers->paginate(25)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'canCreate' => Gate::allows('create', StockTransfer::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', StockTransfer::class);

        $warehouses = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id');

        return view('stock.transfers.create', [
            'warehouses' => $warehouses,
            // Only the products of a form that failed validation are preloaded; the rest is searched on demand.
            'restoredProducts' => Product::query()
                ->whereKey(collect(old('items', []))->pluck('product_id')->filter()->all())
                ->get(['id', 'name', 'sku'])
                ->mapWithKeys(fn (Product $p): array => [$p->getKey() => $p->name.' ('.$p->sku.')'])
                ->all(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StockTransferRequest $request, CreateStockTransferAction $action): RedirectResponse
    {
        Gate::authorize('create', StockTransfer::class);

        ['transfer' => $transfer] = $action->handle($request->validated(), $request->user());

        return redirect()->route('stock-transfers.show', $transfer)->with('status', __('transfers.done', ['number' => $transfer->number]));
    }

    public function show(StockTransfer $stockTransfer): View
    {
        Gate::authorize('view', $stockTransfer);

        return view('stock.transfers.show', [
            'transfer' => $stockTransfer->load(['items', 'fromWarehouse', 'toWarehouse', 'user']),
        ]);
    }
}
