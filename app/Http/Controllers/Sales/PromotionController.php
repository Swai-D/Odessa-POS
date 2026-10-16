<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Sales\Models\Promotion;
use App\Domain\Sales\Services\PromotionEngine;
use App\Domain\Sales\Services\PromotionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Support\Search;
use App\Support\Sort;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PromotionController extends Controller
{
    public function index(Request $request, PromotionService $service, PromotionEngine $engine): View
    {
        Gate::authorize('viewAny', Promotion::class);

        $promotions = Promotion::query()
            ->tap(fn ($q) => Search::apply($q, $request->query('q'), ['name', 'code']));

        [$sort, $dir] = Sort::apply($promotions, $request, ['name' => 'name', 'type' => 'type', 'ends' => 'ends_on'], 'name', 'asc');

        return view('promotions.index', [
            'promotions' => $promotions->paginate(25)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'canManage' => Gate::allows('create', Promotion::class),
            'now' => $service->localParts(now()),
            'engine' => $engine,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Promotion::class);

        return view('promotions.form', $this->formData(new Promotion([
            'type' => Promotion::PERCENT, 'scope' => Promotion::SCOPE_ALL, 'is_active' => true, 'value_kind' => 'percent',
        ])));
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Promotion::class);

        Promotion::query()->create($request->payload() + ['created_by' => $request->user()->getKey()]);

        return redirect()->route('promotions.index')->with('status', __('promotions.saved'));
    }

    public function edit(Promotion $promotion): View
    {
        Gate::authorize('update', $promotion);

        return view('promotions.form', $this->formData($promotion));
    }

    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        Gate::authorize('update', $promotion);

        $promotion->update($request->payload());

        return redirect()->route('promotions.index')->with('status', __('promotions.saved'));
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('update', $promotion);

        $promotion->update(['is_active' => ! $promotion->is_active]);

        return redirect()->route('promotions.index')->with('status', __('promotions.saved'));
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        Gate::authorize('delete', $promotion);

        // Sales keep the promotion's name; only the link is dropped.
        $promotion->delete();

        return redirect()->route('promotions.index')->with('status', __('promotions.deleted'));
    }

    /** @return array<string, mixed> */
    private function formData(Promotion $promotion): array
    {
        $ids = array_map('intval', $promotion->scope_ids ?? []);

        return [
            'promotion' => $promotion,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'selectedProducts' => $promotion->scope === Promotion::SCOPE_PRODUCTS
                ? Product::query()->whereKey($ids)->get(['id', 'name', 'sku'])
                : collect(),
            'selectedIds' => $ids,
        ];
    }
}
