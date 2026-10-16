<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Promotion;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One promotion as typed on the form. Percentages are typed as 12.5 and stored in hundredths (1250); amounts are typed
 * in shillings and stored in minor units. payload() returns the columns ready to save.
 */
class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the PromotionPolicy in the controller.
        return true;
    }

    /** Codes are stored in capitals, so "welcome" and "WELCOME" are the same code when checking for duplicates. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();
        $type = $this->input('type');
        $bulkFixed = $type === Promotion::BULK && $this->input('value_kind') === 'fixed';
        $amountRule = $type === Promotion::FIXED || $bulkFixed
            ? ['required', 'numeric', 'gt:0', 'max:99999999']
            : ['required', 'numeric', 'gt:0', 'max:100'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(Promotion::TYPES)],
            'value_kind' => [Rule::requiredIf($type === Promotion::BULK), 'nullable', Rule::in(['percent', 'fixed'])],
            'value' => $type === Promotion::BOGO ? ['nullable'] : $amountRule,
            'min_qty' => [Rule::requiredIf($type === Promotion::BULK), 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            'buy_qty' => [Rule::requiredIf($type === Promotion::BOGO), 'nullable', 'integer', 'min:1', 'max:1000'],
            'get_qty' => [Rule::requiredIf($type === Promotion::BOGO), 'nullable', 'integer', 'min:1', 'max:1000'],
            'scope' => ['required', Rule::in(Promotion::SCOPES)],
            'scope_ids' => [Rule::requiredIf($this->input('scope') !== Promotion::SCOPE_ALL), 'nullable', 'array', 'max:500'],
            'scope_ids.*' => [
                'integer',
                Rule::exists($this->input('scope') === Promotion::SCOPE_CATEGORIES ? 'categories' : 'products', 'id')->where('tenant_id', $tenantId),
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'days' => ['nullable', 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i'],
            'code' => [
                'nullable', 'string', 'regex:/^[A-Za-z0-9_-]{3,40}$/',
                Rule::unique('promotions', 'code')->where('tenant_id', $tenantId)->ignore($this->route('promotion')),
            ],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $data = $this->validated();
        $type = $data['type'];
        $kind = $type === Promotion::BULK ? $data['value_kind'] : ($type === Promotion::FIXED ? 'fixed' : 'percent');

        $value = match (true) {
            $type === Promotion::BOGO => 0,
            $kind === 'fixed' => Money::toMinor($data['value']),
            default => (int) round((float) $data['value'] * 100),
        };

        $days = collect($data['days'] ?? [])->map(fn ($day): int => (int) $day)->unique()->sort()->values();

        return [
            'name' => $data['name'],
            'type' => $type,
            'value_kind' => $type === Promotion::BULK ? $kind : null,
            'value' => $value,
            'min_qty' => $type === Promotion::BULK ? $data['min_qty'] : null,
            'buy_qty' => $type === Promotion::BOGO ? $data['buy_qty'] : null,
            'get_qty' => $type === Promotion::BOGO ? $data['get_qty'] : null,
            'scope' => $data['scope'],
            'scope_ids' => $data['scope'] === Promotion::SCOPE_ALL ? null : array_values(array_unique(array_map('intval', $data['scope_ids'] ?? []))),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'days' => $days->isEmpty() || $days->count() === 7 ? null : $days->implode(','),
            'time_from' => $data['time_from'] ?? null,
            'time_to' => $data['time_to'] ?? null,
            'code' => ! empty($data['code']) ? strtoupper($data['code']) : null,
            'max_uses' => $data['max_uses'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }
}
