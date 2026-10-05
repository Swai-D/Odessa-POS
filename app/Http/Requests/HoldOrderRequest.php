<?php

namespace App\Http\Requests;

use App\Domain\Sales\Services\SaleCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoldOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'discount' => ['nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::in([
                SaleCalculator::DISCOUNT_NONE, SaleCalculator::DISCOUNT_PERCENT, SaleCalculator::DISCOUNT_FIXED,
            ])],
            'discount.value' => ['required_with:discount', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }
}
