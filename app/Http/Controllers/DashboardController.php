<?php

namespace App\Http\Controllers;

use App\Domain\Sales\Services\DashboardSummary;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary): View
    {
        $tenant = app(TenantContext::class)->get();
        $user = $request->user();

        // A platform super admin has no shop, and a user without the sales permission only gets the greeting.
        if ($tenant === null || $user === null || ! $user->can('sales.view')) {
            return view('dashboard', ['data' => null, 'currency' => config('pos.default_currency')]);
        }

        return view('dashboard', [
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'data' => [
                'today' => $summary->today(),
                'credit' => $summary->outstandingCredit(),
                'days' => $summary->lastDays(),
                'recent' => $summary->recentSales(),
                'low' => $summary->lowStock(),
            ],
        ]);
    }
}
