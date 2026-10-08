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
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'method' => ['required', Rule::in(Payment::methods())],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }
}
