<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\CheckoutAction;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, CheckoutAction $checkout): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        ['sale' => $sale, 'created' => $created] = $checkout->handle($request->validated(), $request->user());

        return response()->json([
            'id' => $sale->getKey(),
            'number' => $sale->number,
            'total' => $sale->total,
            'amount_paid' => $sale->amount_paid,
            'balance_due' => $sale->balance_due,
            'change_given' => $sale->change_given,
            'payment_status' => $sale->payment_status,
            'receipt_url' => route('sales.show', $sale),
        ], $created ? 201 : 200);
    }
}
