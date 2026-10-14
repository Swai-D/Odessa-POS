<?php

namespace App\Http\Requests;

use App\Support\Sms\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->getKey())],
            'phone' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && PhoneNumber::normalize((string) $value) === null) {
                    $fail(__('notifications.phone_invalid'));
                }
            }],
            'locale' => ['required', Rule::in(['en', 'sw'])],
        ];
    }
}
