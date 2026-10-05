<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\ReturnItemsAction;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SaleReturnController extends Controller
{
    public function store(ReturnRequest $request, Sale $sale, ReturnItemsAction $action): RedirectResponse
    {
        Gate::authorize('update', $sale);

        $action->handle($sale, $request->validated(), $request->user());

        return redirect()->route('sales.show', $sale)->with('status', __('pos.sales.return_saved'));
    }
}
