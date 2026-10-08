<?php

namespace App\Http\Controllers;

use App\Support\Search;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared CRUD for simple "lookup" records (categories, brands, units, warehouses) that are
 * managed from one list page with a create/edit modal, exactly like the Dreams POS template.
 */
abstract class LookupController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Route name prefix, e.g. "categories". */
    abstract protected function route(): string;

    /** Translation key for the page title. */
    abstract protected function title(): string;

    /** @return list<array{label: string, value: Closure}> */
    abstract protected function columns(): array;

    /**
     * @return list<array{name: string, label: string, type: string, required?: bool, options?: array<int|string, string>}>
     */
    abstract protected function fields(): array;

    /** @return array<string, mixed> */
    abstract protected function rules(?Model $record): array;

    /** @return list<string> relations to eager load for the list */
    protected function with(): array
    {
        return [];
    }

    /**
     * Hook to add aggregates or filters to the list query.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scope(Builder $query): Builder
    {
        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        return $data;
    }

    /** Return a translated reason when the record must not be deleted. */
    protected function deletionBlockedReason(Model $record): ?string
    {
        return null;
    }

    /** Return a translated reason when a new record must not be created (for example a plan limit). */
    protected function creationBlockedReason(): ?string
    {
        return null;
    }

    /** @return list<string> columns the list search looks in */
    protected function searchColumns(): array
    {
        return ['name'];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', $this->model());

        return view('lookups.index', [
            'title' => $this->title(),
            'route' => $this->route(),
            'columns' => $this->columns(),
            'fields' => $this->fields(),
            'records' => Search::apply($this->scope($this->model()::query()->with($this->with())), $request->query('q'), $this->searchColumns())
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'canManage' => Gate::allows('create', $this->model()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', $this->model());

        if ($reason = $this->creationBlockedReason()) {
            return redirect()->route($this->route().'.index')->withErrors(['limit' => $reason]);
        }

        $data = $request->validate($this->rules(null));
        DB::transaction(fn () => $this->model()::create($this->prepare($data, null)));

        return redirect()->route($this->route().'.index')->with('status', __('app.saved'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $record = $this->find($id);
        Gate::authorize('update', $record);

        $data = $request->validate($this->rules($record));
        DB::transaction(fn () => $record->update($this->prepare($data, $record)));

        return redirect()->route($this->route().'.index')->with('status', __('app.saved'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $record = $this->find($id);
        Gate::authorize('delete', $record);

        if ($reason = $this->deletionBlockedReason($record)) {
            return redirect()->route($this->route().'.index')->withErrors(['delete' => $reason]);
        }

        $record->delete();

        return redirect()->route($this->route().'.index')->with('status', __('app.deleted'));
    }

    protected function find(string $id): Model
    {
        // The BelongsToTenant global scope guarantees records of other tenants are never found.
        return $this->model()::query()->findOrFail($id);
    }

    /** Unique rule limited to the current tenant. */
    protected function uniqueInTenant(string $table, string $column, ?Model $record): Unique
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return Rule::unique($table, $column)
            ->where('tenant_id', $tenantId)
            ->ignore($record?->getKey());
    }
}
