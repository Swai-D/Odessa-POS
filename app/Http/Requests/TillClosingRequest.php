<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TillClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the TillClosingPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'float_kept' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
