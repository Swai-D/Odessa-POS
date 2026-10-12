<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Actions\SubmitPaymentNoticeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentNoticeRequest;
use App\Support\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

class PaymentNoticeController extends Controller
{
    public function store(PaymentNoticeRequest $request, SubmitPaymentNoticeAction $action): RedirectResponse
    {
        $tenant = app(TenantContext::class)->get();
        abort_if($tenant === null, 404);
        // A shop that has been switched off has nothing to report to; everyone else may, even when read-only.
        abort_if(Subscription::current()->state() === Subscription::SUSPENDED, 403);

        $action->handle($tenant, $request->validated(), $request->user());

        return redirect()->route('billing.index')->with('status', __('billing.notice.sent'));
    }
}
