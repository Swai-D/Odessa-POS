<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Actions\ReturnPurchaseItemsAction;
use App\Domain\Purchasing\Models\Purchase;
use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PurchaseReturnController extends Controller
{
    public function store(PurchaseReturnRequest $request, Purchase $purchase, ReturnPurchaseItemsAction $action): RedirectResponse
    {
        Gate::authorize('update', $purchase);

        $action->handle($purchase, $request->validated(), $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', __('purchases.return_saved'));
    }
}
