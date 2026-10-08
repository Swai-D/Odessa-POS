<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Models\Product;
use App\Domain\People\Models\Customer;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Sale;
use App\Support\Plans;
use App\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The navbar search: a few matches from each area the user may see on the shop's plan, each linking onward. */
class SearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $plan = Plans::current();
        $groups = [];

        if (mb_strlen($term) >= 2) {
            if (Gate::allows('viewAny', Product::class)) {
                $groups[] = $this->group('app.menu.products', 'products.index', $term, Search::apply(Product::query(), $term, ['name', 'sku', 'barcode'])
                    ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'sku'])
                    ->map(fn (Product $p): array => ['label' => $p->name, 'detail' => $p->sku, 'url' => route('products.index', ['q' => $p->sku])])->all());
            }

            if (Gate::allows('viewAny', Customer::class)) {
                $groups[] = $this->group('app.menu.customers', 'customers.index', $term, Search::apply(Customer::query(), $term, ['name', 'phone', 'email'])
                    ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'phone'])
                    ->map(fn (Customer $c): array => ['label' => $c->name, 'detail' => $c->phone, 'url' => route('customers.index', ['q' => $c->name])])->all());
            }

            if (Gate::allows('viewAny', Sale::class)) {
                $groups[] = $this->group('app.menu.sales', 'sales.index', $term, Search::apply(Sale::query()->with('customer:id,name'), $term, ['number', 'customer.name'])
                    ->latest('id')->limit(self::LIMIT)->get(['id', 'number', 'customer_id'])
                    ->map(fn (Sale $s): array => ['label' => $s->number, 'detail' => $s->customer?->name, 'url' => route('sales.show', $s)])->all());
            }

            if ($plan->allows('purchasing') && Gate::allows('viewAny', Purchase::class)) {
                $groups[] = $this->group('app.menu.purchases', 'purchases.index', $term, Search::apply(Purchase::query()->with('supplier:id,name'), $term, ['number', 'reference', 'supplier.name'])
                    ->latest('id')->limit(self::LIMIT)->get(['id', 'number', 'supplier_id'])
                    ->map(fn (Purchase $p): array => ['label' => $p->number, 'detail' => $p->supplier?->name, 'url' => route('purchases.show', $p)])->all());
            }

            if ($plan->allows('purchasing') && Gate::allows('viewAny', Supplier::class)) {
                $groups[] = $this->group('app.menu.suppliers', 'suppliers.index', $term, Search::apply(Supplier::query(), $term, ['name', 'phone', 'email'])
                    ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'phone'])
                    ->map(fn (Supplier $s): array => ['label' => $s->name, 'detail' => $s->phone, 'url' => route('suppliers.index', ['q' => $s->name])])->all());
            }
        }

        return view('search.index', [
            'term' => $term,
            'tooShort' => $term !== '' && mb_strlen($term) < 2,
            'groups' => array_values(array_filter($groups, fn (array $g): bool => $g['rows'] !== [])),
        ]);
    }

    /**
     * @param  list<array{label: string, detail: string|null, url: string}>  $rows
     * @return array{title: string, all: string, rows: list<array{label: string, detail: string|null, url: string}>}
     */
    private function group(string $title, string $listRoute, string $term, array $rows): array
    {
        return ['title' => $title, 'all' => route($listRoute, ['q' => $term]), 'rows' => $rows];
    }
}
