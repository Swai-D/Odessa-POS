<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the StockTransferPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'from_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'to_warehouse_id' => ['required', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId)->where('track_stock', true)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ];
    }
}
