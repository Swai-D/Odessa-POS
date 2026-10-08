<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Settings\Actions\RenewSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RenewalRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

/** Records a subscription payment for a shop; super admins only (route middleware `can:platform`). */
class TenantRenewalController extends Controller
{
    public function store(RenewalRequest $request, Tenant $tenant, RenewSubscriptionAction $action): RedirectResponse
    {
        $payment = $action->handle($tenant, $request->validated(), $request->user());

        return redirect()->route('platform.tenants.edit', $tenant)
            ->with('status', __('platform.renewed', ['date' => $payment->period_end->format('Y-m-d')]));
    }
}
