<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformPaymentFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'tenant_id' => ['nullable', 'string', Rule::exists('tenants', 'id')->whereNull('deleted_at')],
            'currency' => ['nullable', 'string', 'size:3', Rule::exists('tenant_payments', 'currency')],
            'method' => ['nullable', Rule::in(Payment::methods())],
        ];
    }

    /** @return array{from: string, to: string, tenant_id: string, currency: string, method: string} */
    public function filters(): array
    {
        $data = $this->validated();
        $to = isset($data['to']) ? today()->parse($data['to']) : today();
        $from = isset($data['from']) ? today()->parse($data['from']) : $to->copy()->startOfMonth();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'tenant_id' => (string) ($data['tenant_id'] ?? ''),
            'currency' => (string) ($data['currency'] ?? ''),
            'method' => (string) ($data['method'] ?? ''),
        ];
    }
}
