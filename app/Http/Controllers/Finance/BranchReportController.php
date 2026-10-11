<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Services\BranchReport;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Money;
use App\Support\ReportPeriod;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Branch (warehouse) comparison for Enterprise shops. Needs the reports permission. */
class BranchReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = ReportPeriod::fromRequest($request);
        $rows = (new BranchReport($from, $to))->rows();

        return view('reports.branches', [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => BranchReport::totals($rows),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = ReportPeriod::fromRequest($request);
        $money = fn (int $amount): string => Money::toMajor($amount);

        $rows = array_map(fn (array $r): array => [
            $r['name'], $r['code'], $r['sales_count'], $money($r['gross']), $money($r['returns']), $money($r['net']),
            $money($r['average']), $money($r['gross_profit']), $money($r['purchases']), $money($r['stock_value']), $r['stock_units'],
        ], (new BranchReport($from, $to))->rows());

        return Csv::download(
            sprintf('branches-%s-%s.csv', $from->format('Ymd'), $to->format('Ymd')),
            [
                __('branches.branch'), __('branches.code'), __('branches.sales_count'), __('branches.gross'), __('branches.returns'),
                __('branches.net'), __('branches.average'), __('branches.gross_profit'), __('branches.purchases'),
                __('branches.stock_value'), __('branches.stock_units'),
            ],
            $rows,
        );
    }
}
