<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the manage-settings gate in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', Rule::in(config('pos.currencies'))],
            'receipt_footer' => ['nullable', 'string', 'max:300'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
        ];
    }
}
