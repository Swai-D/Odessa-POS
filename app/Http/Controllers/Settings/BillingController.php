<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Services\BillingOverview;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class BillingController extends Controller
{
    public function index(BillingOverview $overview): View
    {
        Gate::authorize('manage-settings');

        $tenant = app(TenantContext::class)->get();
        abort_if($tenant === null, 404);

        return view('billing.index', $overview->handle($tenant));
    }
}
