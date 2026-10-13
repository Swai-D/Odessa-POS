<?php

namespace App\Http\Controllers\People;

use App\Domain\People\Models\Customer;
use App\Domain\People\Services\CustomerStatement;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class CustomerStatementController extends Controller
{
    public function show(Request $request, Customer $customer, CustomerStatement $statement): View
    {
        Gate::authorize('view', $customer);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'open' => ['nullable', 'boolean'],
        ]);
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : null;
        $tenant = app(TenantContext::class)->get();

        return view('customers.statement', [
            'customer' => $customer,
            'statement' => $statement->handle($customer, $from, $to, (bool) ($filters['open'] ?? false)),
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '', 'open' => (bool) ($filters['open'] ?? false)],
            'shopName' => (string) ((new TenantSettings)->get('business_name') ?: $tenant?->name),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
        ]);
    }
}
