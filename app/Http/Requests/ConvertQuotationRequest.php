<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertQuotationRequest extends FormRequest
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
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'payment_method' => ['nullable', Rule::in(Payment::methods())],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
