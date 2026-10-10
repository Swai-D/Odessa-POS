<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Settings\Actions\SaveSubscriptionPlanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', [
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'currency' => 'TZS',
        ]);
    }

    public function create(): View
    {
        return view('platform.plans.form', ['plan' => new SubscriptionPlan(['is_active' => true]), 'features' => config('plans.features')]);
    }

    public function store(SubscriptionPlanRequest $request, SaveSubscriptionPlanAction $action): RedirectResponse
    {
        $action->handle($request->validated());

        return redirect()->route('platform.plans.index')->with('status', __('platform.plans_saved'));
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('platform.plans.form', ['plan' => $plan, 'features' => config('plans.features')]);
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $plan, SaveSubscriptionPlanAction $action): RedirectResponse
    {
        $action->handle($request->validated(), $plan);

        return redirect()->route('platform.plans.index')->with('status', __('platform.plans_saved'));
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        $aliases = array_keys(array_filter((array) config('plans.aliases'), fn (string $code): bool => $code === $plan->code));
        $codes = [$plan->code, ...$aliases];
        $inUse = Tenant::withTrashed()->whereIn('plan', $codes)->exists()
            || TenantPayment::query()->whereIn('plan', $codes)->exists();

        if ($inUse) {
            return redirect()->route('platform.plans.index')->withErrors(['plan' => __('platform.plan_in_use')]);
        }

        $plan->delete();

        return redirect()->route('platform.plans.index')->with('status', __('platform.plan_deleted'));
    }
}
