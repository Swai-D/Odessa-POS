<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Actions\ImportProductsAction;
use App\Domain\Catalog\Actions\ProductImportException;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductImportRequest;
use App\Support\Csv;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Download the product list as CSV, and load products (and opening stock) from a CSV file. */
class ProductTransferController extends Controller
{
    public function export(): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);

        // Read everything now: a streamed response runs after the request, when the shop context is already gone.
        $rows = Product::query()->with(['category:id,name', 'brand:id,name', 'unit:id,name'])->withSum('stocks', 'quantity')
            ->orderBy('name')->orderBy('id')->get()
            ->map(fn (Product $p): array => [
                $p->sku, $p->name, $p->barcode ?? '', $p->type, $p->category->name ?? '', $p->brand->name ?? '', $p->unit->name ?? '',
                Money::toMajor($p->cost_price), Money::toMajor($p->selling_price), $p->tax_rate, $p->tax_inclusive ? 'yes' : 'no',
                $p->track_stock ? 'yes' : 'no', $p->alert_quantity, $p->is_active ? 'yes' : 'no', '', (float) ($p->stocks_sum_quantity ?? 0),
            ])->all();

        return Csv::download('products-'.now()->format('Ymd').'.csv', [...ImportProductsAction::COLUMNS, 'stock'], $rows);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', Product::class);

        return Csv::download('products-template.csv', ImportProductsAction::COLUMNS, [
            ['SOAP-250', 'Soap 250g', '6001234567890', 'standard', 'Household', '', 'Piece', '1500', '2500', '0', 'yes', 'yes', '10', 'yes', '50'],
            ['DELIVERY', 'Delivery fee', '', 'service', '', '', '', '0', '3000', '0', 'yes', 'no', '', 'yes', ''],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('products.import', ['columns' => ImportProductsAction::COLUMNS, 'max' => (int) config('pos.import_max_rows')]);
    }

    public function store(ProductImportRequest $request, ImportProductsAction $action): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        try {
            $result = $action->handle($request->file('file')?->getRealPath() ?? '', $request->user());
        } catch (ProductImportException $e) {
            return redirect()->route('products.import')->with(['import_problems' => $e->problems, 'import_total' => $e->total]);
        }

        return redirect()->route('products.import')->with('import_result', $result);
    }
}
