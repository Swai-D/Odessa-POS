<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\PaymentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The cashier asks a customer's phone to pay. Amount is in minor units, like everywhere else in the till. */
class MobileMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the SalePolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'provider' => ['required', Rule::in(PaymentRequest::PROVIDERS)],
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'integer', 'min:100', 'max:99999999999'],
        ];
    }
}
