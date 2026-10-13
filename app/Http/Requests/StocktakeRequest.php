<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StocktakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'warehouse_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
