<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'code' => [
                ...($plan === null ? ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('subscription_plans', 'code')] : ['prohibited']),
            ],
            'name' => ['required', 'string', 'max:100'],
            'monthly_price' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'annual_price' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in([...config('plans.features', []), '*'])],
            'limit_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit_warehouses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
