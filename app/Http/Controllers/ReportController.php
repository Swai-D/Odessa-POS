<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Services\ReportService;
use App\Support\Money;
use App\Support\Plans;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** @return array{0: Carbon, 1: Carbon} the validated period (default: last 30 days, at most a year) */
    private function period(Request $request): array
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

        return [$from, $to];
    }

    /** Download one report as a CSV file that opens cleanly in Excel. */
    public function export(Request $request, string $report): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $reports = new ReportService($from, $to);
        $money = fn (int $amount): string => Money::toMajor($amount);

        [$header, $rows] = match ($report) {
            'daily' => [
                [__('pos.sales.date'), __('reports.sales'), __('pos.sales.total')],
                array_map(fn (array $d): array => [$d['date'], $d['count'], $money($d['total'])], $reports->salesByDay()),
            ],
            'products' => [
                [__('reports.product'), __('reports.quantity'), __('reports.revenue'), __('reports.profit')],
                array_map(fn (array $r): array => [$r['name'], $r['quantity'], $money($r['revenue']), $money($r['profit'])], $reports->topProducts(1000)),
            ],
            'payments' => [
                [__('reports.method'), __('pos.sales.total')],
                array_map(fn (array $r): array => [__('pos.methods.'.$r['method']), $money($r['amount'])], $reports->paymentsByMethod()),
            ],
            'balances' => [
                [__('pos.sales.customer'), __('reports.balance')],
                array_map(fn (array $r): array => [$r['name'], $money($r['balance'])], $reports->customerBalances(100000)),
            ],
            default => [
                [__('reports.product'), __('reports.on_hand'), __('reports.alert_quantity')],
                $reports->lowStock(100000)->map(fn ($p): array => [$p->name, (float) ($p->getAttribute('on_hand') ?? 0), (float) $p->alert_quantity])->all(),
            ],
        };

        $filename = sprintf('%s-%s-%s.csv', $report, $from->format('Ymd'), $to->format('Ymd'));

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Swahili text correctly.
            fputcsv($out, array_map(self::cell(...), $header));
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formulas in text coming from shop data (names typed by users). */
    private static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public function index(Request $request): View
    {
        [$from, $to] = $this->period($request);

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
