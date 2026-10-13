<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Services\AuditRecorder;
use App\Domain\Settings\Services\DataExport;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Lets the Owner download the shop's own data as CSV files. */
class DataExportController extends Controller
{
    public function index(DataExport $export): View
    {
        Gate::authorize('manage-settings');

        return view('settings.export', ['keys' => $export->keys()]);
    }

    public function download(string $dataset, DataExport $export, AuditRecorder $audit): StreamedResponse
    {
        Gate::authorize('manage-settings');
        abort_unless(in_array($dataset, $export->keys(), true), 404);

        $tenant = app(TenantContext::class)->get();
        abort_if($tenant === null, 404);

        $audit->note('data_exported', 'Export', null, $dataset);

        return Csv::download(sprintf('%s-%s-%s.csv', $tenant->slug, $dataset, now()->format('Ymd')), $export->header($dataset), $export->rows($dataset));
    }
}
