<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Category;
use App\Domain\Inventory\Actions\CompleteStocktakeAction;
use App\Domain\Inventory\Actions\SaveStocktakeCountsAction;
use App\Domain\Inventory\Actions\StartStocktakeAction;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StocktakeCountsRequest;
use App\Http\Requests\StocktakeRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StocktakeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Stocktake::class);

        return view('stocktakes.index', [
            'stocktakes' => Stocktake::query()->with(['warehouse:id,name', 'user:id,name'])->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted')])->latest('id')->paginate(25),
            'canManage' => Gate::allows('create', Stocktake::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Stocktake::class);

        return view('stocktakes.create', [
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
            'categories' => Category::query()->orderBy('name')->pluck('name', 'id'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(StocktakeRequest $request, StartStocktakeAction $action): RedirectResponse
    {
        Gate::authorize('create', Stocktake::class);

        ['stocktake' => $stocktake] = $action->handle($request->validated(), $request->user());

        return redirect()->route('stocktakes.show', $stocktake)->with('status', __('stocktake.started_msg', ['number' => $stocktake->number]));
    }

    public function show(Request $request, Stocktake $stocktake): View
    {
        Gate::authorize('view', $stocktake);

        $stocktake->load(['warehouse:id,name', 'user:id,name']);
        $differencesOnly = ! $stocktake->isOpen() && $request->boolean('differences');
        $items = $stocktake->items()->orderBy('product_name')->orderBy('id')
            ->when($differencesOnly, fn ($q) => $q->whereNotNull('counted')->whereColumn('counted', '!=', 'expected'))
            ->paginate(100)->withQueryString();

        return view('stocktakes.show', [
            'stocktake' => $stocktake,
            'items' => $items,
            'differencesOnly' => $differencesOnly,
            'totals' => [
                'lines' => $stocktake->items()->count(),
                'counted' => $stocktake->items()->whereNotNull('counted')->count(),
                'different' => $stocktake->items()->whereNotNull('counted')->whereColumn('counted', '!=', 'expected')->count(),
            ],
            'canManage' => Gate::allows('update', $stocktake),
        ]);
    }

    /** Saves the typed counts; with "complete" set it then finishes the count, so nothing typed is ever lost. */
    public function update(StocktakeCountsRequest $request, Stocktake $stocktake, SaveStocktakeCountsAction $save, CompleteStocktakeAction $complete): RedirectResponse
    {
        Gate::authorize('update', $stocktake);

        $save->handle($stocktake, $request->validated('counts'));

        if ($request->boolean('complete')) {
            $result = $complete->handle($stocktake, $request->user());

            return redirect()->route('stocktakes.show', $stocktake)->with('status', __('stocktake.completed', ['count' => $result['adjusted']]));
        }

        return back()->with('status', __('stocktake.saved'));
    }

    public function cancel(Stocktake $stocktake): RedirectResponse
    {
        Gate::authorize('update', $stocktake);

        if ($stocktake->isOpen()) {
            $stocktake->update(['status' => Stocktake::CANCELLED]);
        }

        return redirect()->route('stocktakes.index')->with('status', __('stocktake.cancelled'));
    }
}
