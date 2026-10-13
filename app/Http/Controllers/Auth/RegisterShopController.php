<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Settings\Actions\RegisterShopAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterShopRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/** Public sign-up that opens a trial shop. Only on the main address, never on a shop's own address. */
class RegisterShopController extends Controller
{
    public function create(TenantContext $context): View
    {
        $this->guard($context);

        return view('auth.register', ['trialDays' => (int) config('plans.trial_days')]);
    }

    public function store(RegisterShopRequest $request, TenantContext $context, RegisterShopAction $action): RedirectResponse
    {
        $this->guard($context);

        // Real people never see this field; a bot that fills every input is sent away quietly.
        if ($request->filled('website')) {
            return redirect()->route('login');
        }

        $data = $request->validated();

        $owner = $action->handle([
            'shop_name' => $data['shop_name'],
            'owner_name' => $data['owner_name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ], app()->getLocale());

        $request->session()->put('tenant_slug', (string) Tenant::query()->whereKey($owner->tenant_id)->value('slug'));
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', __('auth.register.welcome'));
    }

    private function guard(TenantContext $context): void
    {
        abort_unless(config('plans.self_signup'), 404);

        // A shop's own address (subdomain or custom domain) is for that shop's staff, not for opening new shops.
        $domainUrl = str_contains((string) config('app.domain'), '://') ? (string) config('app.domain') : 'http://'.config('app.domain');
        $appDomain = strtolower((string) parse_url($domainUrl, PHP_URL_HOST));
        abort_if($context->get() !== null && strtolower(request()->getHost()) !== $appDomain, 404);
    }
}
