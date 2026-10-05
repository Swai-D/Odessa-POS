<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Goods-received form. Costs and payment amounts are entered in major units (e.g. 1500.50). */
class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the PurchasePolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()->getKey();

        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'update_cost' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_method' => ['nullable', Rule::in(Payment::methods())],
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
