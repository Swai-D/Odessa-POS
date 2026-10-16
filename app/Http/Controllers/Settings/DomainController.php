<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Services\CustomDomainService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The shop's own web address: ask for it, prove it, see whether it works, remove it. */
class DomainController extends Controller
{
    public function show(CustomDomainService $domains): View
    {
        Gate::authorize('manage-settings');
        $tenant = $this->tenant();

        return view('settings.domain', [
            'tenant' => $tenant,
            'steps' => $domains->instructions($tenant),
            'target' => $domains->target(),
            'sharedLink' => url('/t/'.$tenant->slug),
        ]);
    }

    public function store(Request $request, CustomDomainService $domains): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $tenant = $this->tenant();

        $host = $domains->normalize((string) $request->input('domain'));
        $problem = $domains->problem($host, $tenant);

        if ($problem !== null) {
            return back()->withInput()->withErrors(['domain' => __('domain.errors.'.$problem)]);
        }

        $domains->start($tenant, $host);

        return redirect()->route('settings.domain')->with('status', __('domain.started'));
    }

    public function check(CustomDomainService $domains): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $tenant = $this->tenant();

        if ($tenant->domain_pending) {
            $result = $domains->verify($tenant);
            $back = redirect()->route('settings.domain');

            return $result === 'verified' ? $back->with('status', __('domain.result.verified')) : $back->withErrors(['domain' => __('domain.result.'.$result)]);
        }

        $routed = $domains->refreshRouting($tenant);

        $back = redirect()->route('settings.domain');

        return $routed ? $back->with('status', __('domain.result.routed')) : $back->withErrors(['domain' => __('domain.result.not_routed')]);
    }

    public function destroy(CustomDomainService $domains): RedirectResponse
    {
        Gate::authorize('manage-settings');
        $tenant = $this->tenant();

        // A domain the platform team set (not proven by the shop) is theirs to change.
        if ($tenant->domain && $tenant->domain_verified_at === null) {
            abort(403);
        }

        $tenant->domain_pending ? $domains->cancelPending($tenant) : $domains->remove($tenant);

        return redirect()->route('settings.domain')->with('status', __('domain.removed'));
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(TenantContext::class)->get()?->getKey());
    }
}
