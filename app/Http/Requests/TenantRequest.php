<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create and update share the shop fields; creating also takes the first owner. */
class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $id = $this->route()->hasParameter('tenant') ? $tenant->getKey() : null;
        $requiresPaidUntilOnActivation = $id === null
            || ($tenant->status !== 'active' && $tenant->paid_until === null);
        $availablePlans = SubscriptionPlan::query()->where('is_active', true)->pluck('code')->all();
        foreach ((array) config('plans.aliases') as $alias => $code) {
            if (in_array($code, $availablePlans, true)) {
                $availablePlans[] = $alias;
            }
        }
        if ($id !== null && $tenant->plan !== null) {
            $availablePlans[] = $tenant->plan;
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:190', Rule::unique('tenants', 'domain')->ignore($id)],
            'status' => ['required', Rule::in(['active', 'trial', 'suspended'])],
            'plan' => ['required', Rule::in(array_unique($availablePlans))],
            'paid_until' => [
                'nullable', 'date',
                Rule::requiredIf(fn (): bool => $this->input('status') === 'active' && $requiresPaidUntilOnActivation),
            ],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:today'],
            'override_features' => ['nullable', 'array'],
            'override_features.*' => [Rule::in(config('plans.features'))],
            'limit_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit_warehouses' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];

        if ($id === null) {
            $rules['slug'] = ['required', 'string', 'max:60', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::unique('tenants', 'slug')];
            $rules['owner_name'] = ['required', 'string', 'max:120'];
            $rules['owner_email'] = ['required', 'email', 'max:190', Rule::unique('users', 'email')];
            $rules['owner_password'] = ['required', 'string', 'min:8', 'max:100'];
        }

        return $rules;
    }
}
