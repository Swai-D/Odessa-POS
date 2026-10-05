<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Services\SaleCalculator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The cart as sent by the POS screen. Money values are integer minor units. Only product ids and
 * quantities are trusted from the client; prices, tax and totals are computed on the server.
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the SalePolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'discount' => ['nullable', 'array'],
            'discount.type' => ['required_with:discount', Rule::in([
                SaleCalculator::DISCOUNT_NONE, SaleCalculator::DISCOUNT_PERCENT, SaleCalculator::DISCOUNT_FIXED,
            ])],
            'discount.value' => ['required_with:discount', 'numeric', 'min:0', 'max:99999999999'],
            'payments' => ['nullable', 'array', 'max:10'],
            'payments.*.method' => ['required', Rule::in(Payment::methods())],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:99999999999'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
