<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\Actions\SaveIntegrationAction;
use App\Domain\Integrations\Models\TenantIntegration;
use App\Domain\Integrations\Services\IntegrationRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationRequest;
use App\Support\Tenancy\TenantContext;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class IntegrationController extends Controller
{
    public function index(IntegrationRegistry $registry): View
    {
        Gate::authorize('manage-settings');

        $settings = new TenantSettings;
        $saved = TenantIntegration::query()->get()->keyBy('channel');
        $channels = [];

        foreach ($registry->channels() as $channel) {
            $row = $saved->get($channel);
            $drivers = [];

            foreach ($registry->drivers($channel) as $key => $class) {
                $drivers[$key] = [
                    'label' => $class::label(),
                    'fields' => $class::fields(),
                    // Only values of the saved driver are shown; secrets are never sent back to the browser.
                    'values' => $row?->driver === $key ? ($row->settings ?? []) : [],
                    'savedSecrets' => $row?->driver === $key ? array_keys($row->secrets ?? []) : [],
                ];
            }

            $channels[$channel] = [
                'enabled' => $settings->feature($registry->feature($channel)),
                'driver' => $row?->driver,
                'drivers' => $drivers,
            ];
        }

        return view('settings.integrations', ['channels' => $channels]);
    }

    public function update(IntegrationRequest $request, SaveIntegrationAction $action): RedirectResponse
    {
        Gate::authorize('manage-settings');

        $action->handle(app(TenantContext::class)->get(), $request->channel(), $request->validated());

        return redirect()->route('settings.integrations')->with('status', __('app.saved'));
    }
}
