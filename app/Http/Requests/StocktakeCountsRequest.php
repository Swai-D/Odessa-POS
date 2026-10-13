<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StocktakeCountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the policy.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counts' => ['required', 'array', 'max:500'],
            'counts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ];
    }
}
