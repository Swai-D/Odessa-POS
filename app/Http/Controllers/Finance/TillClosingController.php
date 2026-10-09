<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\CloseTillAction;
use App\Domain\Finance\Models\TillClosing;
use App\Domain\Finance\Services\TillSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\TillClosingRequest;
use App\Policies\TillClosingPolicy;
use App\Support\Money;
use App\Support\Search;
use App\Support\Sort;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class TillClosingController extends Controller
{
    private function currency(): string
    {
        return (string) (new TenantSettings)->get('currency', config('pos.default_currency'));
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TillClosing::class);

        $user = $request->user();
        $seesAll = $user !== null && TillClosingPolicy::seesAll($user);

        $closings = TillClosing::query()
            ->with('user:id,name')
            ->when(! $seesAll, fn (Builder $q) => $q->where('user_id', $user?->getKey()))
            ->tap(fn (Builder $q) => Search::apply($q, $request->query('q'), ['note', 'user.name']));

        [$sort, $dir] = Sort::apply($closings, $request, ['date' => 'period_end', 'difference' => 'difference'], 'date', 'desc');

        return view('till.index', [
            'closings' => $closings->paginate(25)->withQueryString(),
            'sort' => $sort,
            'dir' => $dir,
            'seesAll' => $seesAll,
            'canClose' => Gate::allows('create', TillClosing::class),
            'currency' => $this->currency(),
        ]);
    }

    public function create(Request $request, TillSummary $summary): View
    {
        Gate::authorize('create', TillClosing::class);

        return view('till.create', [
            'figures' => $summary->for($request->user()),
            'currency' => $this->currency(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(TillClosingRequest $request, CloseTillAction $action): RedirectResponse
    {
        Gate::authorize('create', TillClosing::class);

        $data = $request->validated();

        ['closing' => $closing] = $action->handle($request->user(), [
            'idempotency_key' => $data['idempotency_key'],
            'counted_cash' => Money::toMinor($data['counted_cash']),
            'float_kept' => Money::toMinor($data['float_kept']),
            'note' => $data['note'] ?? null,
        ]);

        return redirect()->route('till-closings.show', $closing)->with('status', __('till.done'));
    }

    public function show(TillClosing $tillClosing): View
    {
        Gate::authorize('view', $tillClosing);

        return view('till.show', [
            'closing' => $tillClosing->load('user:id,name'),
        ]);
    }
}
