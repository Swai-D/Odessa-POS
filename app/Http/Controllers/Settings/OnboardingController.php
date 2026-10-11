<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Services\OnboardingChecklist;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class OnboardingController extends Controller
{
    public function dismiss(OnboardingChecklist $checklist): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $tenant = app(TenantContext::class)->get();
        abort_if($tenant === null, 404);

        $checklist->dismiss($tenant);

        return redirect()->route('dashboard');
    }
}
