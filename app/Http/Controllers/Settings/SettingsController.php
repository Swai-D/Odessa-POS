<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\Services\IntegrationRegistry;
use App\Domain\Settings\Actions\UpdateTenantSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function edit(IntegrationRegistry $registry): View
    {
        Gate::authorize('manage-settings');

        $settings = new TenantSettings;
        $features = [];
        foreach (array_diff(array_keys(config('pos.features')), $registry->ownedFeatures()) as $feature) {
            $features[$feature] = $settings->feature($feature);
        }

        return view('settings.edit', [
            'businessName' => (string) $settings->get('business_name', app(TenantContext::class)->get()->name),
            'currency' => (string) $settings->get('currency', config('pos.default_currency')),
            'receiptFooter' => (string) $settings->get('receipt_footer', ''),
            'currencies' => config('pos.currencies'),
            'features' => $features,
        ]);
    }

    public function update(SettingsRequest $request, UpdateTenantSettingsAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle(app(TenantContext::class)->get(), $request->validated());

        return redirect()->route('settings.index')->with('status', __('app.saved'));
    }
}
