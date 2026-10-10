<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', Rule::in([1, 12])],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'discount_reason' => [Rule::requiredIf(fn (): bool => (float) $this->input('discount_amount', 0) > 0), 'nullable', 'string', 'max:250'],
            'method' => ['required', Rule::in(Payment::methods())],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }
}
