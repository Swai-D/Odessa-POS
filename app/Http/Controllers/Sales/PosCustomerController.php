<?php

namespace App\Http\Controllers\Sales;

use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosCustomerRequest;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Quick "add customer" from the till. Full customer management lives in CustomerController. */
class PosCustomerController extends Controller
{
    /** Customer search for the till's picker: by name or phone, or one customer by id (to restore a held order). */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        $query = Customer::query()->where('is_active', true);

        if ($request->filled('id')) {
            $query->whereKey($request->integer('id'));
        } else {
            Search::apply($query, $request->query('q'), ['name', 'phone']);
        }

        return response()->json([
            'data' => $query->orderBy('name')->limit(20)->get(['id', 'name', 'phone'])->map(fn (Customer $c): array => [
                'id' => $c->getKey(), 'name' => $c->name, 'phone' => $c->phone,
            ])->all(),
        ]);
    }

    public function store(PosCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::create($request->validated() + ['is_active' => true]);

        return response()->json(['id' => $customer->getKey(), 'name' => $customer->name, 'phone' => $customer->phone], 201);
    }
}
