<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Services\ReportService;
use App\Support\Plans;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->is_super_admin || $user->can('reports.view')), 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to']) : Carbon::today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->subDays(29);

        // Keep the daily table readable and the queries cheap.
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365);
        }

        $reports = new ReportService($from, $to);

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'summary' => $reports->salesSummary(),
            'days' => array_reverse($reports->salesByDay()),
            'top' => $reports->topProducts(),
            'methods' => $reports->paymentsByMethod(),
            'balances' => $reports->customerBalances(),
            'stock' => $reports->stockValuation(),
            'low' => $reports->lowStock(),
            'purchases' => Plans::current()->allows('purchasing') ? $reports->purchases() : null,
        ]);
    }
}
