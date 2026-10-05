<?php

namespace App\Http\Controllers\Sales;

use App\Domain\People\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosCustomerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Quick "add customer" from the till. Full customer management lives in CustomerController. */
class PosCustomerController extends Controller
{
    public function store(PosCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::create($request->validated() + ['is_active' => true]);

        return response()->json(['id' => $customer->getKey(), 'name' => $customer->name, 'phone' => $customer->phone], 201);
    }
}
