<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Actions\SaveExpenseAction;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Sales\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Support\Csv;
use App\Support\Money;
use App\Support\Search;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    /**
     * The filtered list: a date range (default: this month), an optional category and a text search.
     *
     * @return array{0: Builder<Expense>, 1: Carbon, 2: Carbon, 3: int|null}
     */
    private function filtered(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'category' => ['nullable', 'integer'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to']) : Carbon::today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->startOfMonth();
        $category = isset($data['category']) ? (int) $data['category'] : null;

        $query = Expense::query()
            ->with(['category:id,name', 'user:id,name'])
            ->whereBetween('spent_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->when($category, fn (Builder $q) => $q->where('expense_category_id', $category))
            ->tap(fn (Builder $q) => Search::apply($q, $request->query('q'), ['reference', 'note', 'category.name']));

        return [$query, $from, $to, $category];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        [$query, $from, $to, $category] = $this->filtered($request);

        return view('expenses.index', [
            'expenses' => (clone $query)->orderByDesc('spent_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'total' => (int) (clone $query)->sum('amount'),
            'from' => $from,
            'to' => $to,
            'category' => $category,
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'methods' => Payment::methods(),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'canManage' => Gate::allows('create', Expense::class),
        ]);
    }

    public function store(ExpenseRequest $request, SaveExpenseAction $action): RedirectResponse
    {
        Gate::authorize('create', Expense::class);

        $action->handle(null, $request->validated(), $request->user());

        return redirect()->route('expenses.index')->with('status', __('app.saved'));
    }

    public function update(ExpenseRequest $request, string $expense, SaveExpenseAction $action): RedirectResponse
    {
        $record = Expense::query()->findOrFail($expense);
        Gate::authorize('update', $record);

        $action->handle($record, $request->validated(), $request->user());

        return redirect()->route('expenses.index')->with('status', __('app.saved'));
    }

    public function destroy(string $expense): RedirectResponse
    {
        $record = Expense::query()->findOrFail($expense);
        Gate::authorize('delete', $record);

        $record->delete();

        return redirect()->route('expenses.index')->with('status', __('app.deleted'));
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Expense::class);

        [$query, $from, $to] = $this->filtered($request);

        $rows = $query->orderBy('spent_on')->orderBy('id')->get()->map(fn (Expense $e): array => [
            $e->spent_on->format('Y-m-d'),
            $e->category->name ?? '',
            __('pos.methods.'.$e->method),
            $e->reference ?? '',
            $e->note ?? '',
            Money::toMajor($e->amount),
        ])->all();

        return Csv::download(
            sprintf('expenses-%s-%s.csv', $from->format('Ymd'), $to->format('Ymd')),
            [__('expenses.date'), __('expenses.category'), __('expenses.method'), __('expenses.reference'), __('expenses.note'), __('expenses.amount')],
            $rows,
        );
    }
}
