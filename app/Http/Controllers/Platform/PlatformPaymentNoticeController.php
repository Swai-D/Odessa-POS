<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PaymentNotice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The customer-care inbox of "we have paid" messages. Handling one only marks it done; renewing is a separate, deliberate step. */
class PlatformPaymentNoticeController extends Controller
{
    public function index(Request $request): View
    {
        $showAll = $request->query('status') === 'all';
        $query = PaymentNotice::query()->with('tenant', 'user');

        if (! $showAll) {
            $query->where('status', PaymentNotice::PENDING);
        }

        return view('platform.notices.index', [
            'notices' => $query->latest()->paginate(25)->withQueryString(),
            'showAll' => $showAll,
            'pendingCount' => PaymentNotice::query()->where('status', PaymentNotice::PENDING)->count(),
        ]);
    }

    public function handle(Request $request, PaymentNotice $notice): RedirectResponse
    {
        if ($notice->status === PaymentNotice::PENDING) {
            $notice->update(['status' => PaymentNotice::HANDLED, 'handled_by' => $request->user()?->getKey(), 'handled_at' => now()]);
        }

        return redirect()->back()->with('status', __('platform.notices.marked'));
    }
}
