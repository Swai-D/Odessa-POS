<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('plans.self_signup');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'terms' => ['accepted'],
        ];
    }
}
