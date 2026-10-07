<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Platform-level shop management; reachable by super admins only (route middleware `can:platform`). */
class TenantController extends Controller
{
    public function index(): View
    {
        return view('platform.tenants.index', [
            'tenants' => Tenant::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.create', ['plans' => array_keys(config('plans.plans'))]);
    }

    public function store(TenantRequest $request, ProvisionTenantAction $action): RedirectResponse
    {
        $data = $request->validated();

        $tenant = $action->handle([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?? null,
            'plan' => $data['plan'],
            'status' => $data['status'],
            'paid_until' => $data['paid_until'] ?? null,
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_password' => $data['owner_password'],
        ]);

        $this->saveOverrides($tenant, $data);

        return redirect()->route('platform.tenants.index')->with('status', __('platform.created'));
    }

    public function edit(Tenant $tenant): View
    {
        /** @var array<string, mixed> $settings */
        $settings = $tenant->settings ?? [];

        return view('platform.tenants.edit', [
            'tenant' => $tenant,
            'plans' => array_keys(config('plans.plans')),
            'overrides' => (array) ($settings['plan_overrides'] ?? []),
        ]);
    }

    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();

        $tenant->update([
            'name' => $data['name'],
            'domain' => $data['domain'] ?? null,
            'status' => $data['status'],
            'plan' => $data['plan'],
            'paid_until' => $data['paid_until'] ?? null,
        ]);

        $this->saveOverrides($tenant, $data);

        return redirect()->route('platform.tenants.index')->with('status', __('platform.saved'));
    }

    /** @param  array<string, mixed>  $data */
    private function saveOverrides(Tenant $tenant, array $data): void
    {
        /** @var array<string, mixed> $settings */
        $settings = $tenant->settings ?? [];

        $overrides = array_filter([
            'features' => array_values((array) ($data['override_features'] ?? [])),
            'limits' => array_filter([
                'users' => isset($data['limit_users']) ? (int) $data['limit_users'] : null,
                'warehouses' => isset($data['limit_warehouses']) ? (int) $data['limit_warehouses'] : null,
            ], fn ($value): bool => $value !== null),
        ]);

        if ($overrides === []) {
            unset($settings['plan_overrides']);
        } else {
            $settings['plan_overrides'] = $overrides;
        }

        $tenant->update(['settings' => $settings]);
    }
}
