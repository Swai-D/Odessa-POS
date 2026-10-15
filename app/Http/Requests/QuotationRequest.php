<?php

namespace App\Http\Requests;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A quotation as typed on screen. Only product ids and quantities are trusted; prices come from the server. */
class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the QuotationPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ];
    }
}
