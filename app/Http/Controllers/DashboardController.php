<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Services\ReportService;
use App\Domain\Sales\Services\DashboardSummary;
use App\Support\Plans;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $now = Carbon::now();
        $profit = (new ReportService($now->copy()->startOfMonth(), $now->copy()->endOfMonth()))->salesProfitSummary();
        $canSeeExpenses = Plans::current()->allows('expenses') && $user->can('expenses.view');
        $expenses = $canSeeExpenses ? $summary->expensesThisMonth() : null;
        // Basic shops see the net-profit card locked, as a reason to move up; users without the permission see nothing.
        $netProfitPlan = ! Plans::current()->allows('expenses') && $user->can('expenses.view')
            ? Plans::cheapestPlanFor('expenses')
            : null;

        return view('dashboard', [
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'data' => [
                'today' => $summary->today(),
                'sales_month' => $summary->salesThisMonth(),
                'gross_profit' => $profit['gross_profit'],
                'cogs' => $profit['cost'],
                'credit' => $summary->outstandingCredit(),
                'days' => $summary->lastDays(),
                'recent' => $summary->recentSales(),
                'low' => $summary->lowStock(),
                'low_count' => $summary->lowStockCount(),
                'products_count' => $summary->activeProductCount(),
                // Only shops whose plan has expenses, and users who may see them.
                'expenses' => $expenses,
                'net_profit' => $expenses === null ? null : $profit['gross_profit'] - $expenses,
                'net_profit_plan' => $netProfitPlan,
            ],
        ]);
    }
}
