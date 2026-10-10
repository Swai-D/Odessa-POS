<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Sales\Models\Payment;
use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

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
        return view('platform.tenants.create', [
            'plans' => $this->planOptions(),
            'trialEndsAt' => now()->addDays((int) config('plans.trial_days'))->format('Y-m-d'),
        ]);
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
            'paid_until' => $data['status'] === 'trial' ? null : ($data['paid_until'] ?? null),
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
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
            'plans' => $this->planOptions($tenant),
            'billingPlan' => SubscriptionPlan::query()->where('code', config("plans.aliases.{$tenant->plan}", $tenant->plan))->first(),
            'overrides' => (array) ($settings['plan_overrides'] ?? []),
            'payments' => TenantPayment::query()->where('tenant_id', $tenant->getKey())->with('user')->latest('id')->limit(20)->get(),
            'methods' => Payment::methods(),
            'idempotencyKey' => (string) Str::uuid(),
            'currency' => 'TZS',
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
            'paid_until' => $data['status'] === 'trial' ? null : ($data['paid_until'] ?? null),
            'trial_ends_at' => $data['status'] === 'trial'
                ? ($data['trial_ends_at'] ?? ($tenant->status === 'trial' && $tenant->trial_ends_at !== null
                    ? $tenant->trial_ends_at
                    : now()->addDays((int) config('plans.trial_days'))))
                : null,
        ]);

        $this->saveOverrides($tenant, $data);

        return redirect()->route('platform.tenants.index')->with('status', __('platform.saved'));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        return redirect()->route('platform.tenants.index')->with('status', __('platform.deleted'));
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

    /** @return array<string, string> */
    private function planOptions(?Tenant $tenant = null): array
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get()->mapWithKeys(
            fn (SubscriptionPlan $plan): array => [$plan->code => $plan->name],
        )->all();

        foreach ((array) config('plans.aliases') as $alias => $code) {
            if (isset($plans[$code])) {
                $key = 'platform.plans.'.$alias;
                $label = __($key);
                $plans[$alias] = $label === $key ? ucfirst((string) $alias) : $label;
            }
        }

        if ($tenant?->plan !== null && ! isset($plans[$tenant->plan])) {
            $current = SubscriptionPlan::query()->where('code', $tenant->plan)->first();
            $plans[$tenant->plan] = $current->name ?? ucfirst($tenant->plan);
        }

        return $plans;
    }
}
