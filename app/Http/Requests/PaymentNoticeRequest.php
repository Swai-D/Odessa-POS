<?php

namespace App\Http\Requests;

use App\Models\PaymentNotice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-settings') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(PaymentNotice::methods())],
            'months' => ['required', 'integer', Rule::in([1, 12])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'reference' => ['required', 'string', 'min:4', 'max:100'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }
}
