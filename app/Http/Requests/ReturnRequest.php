<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.sale_item_id' => ['required', 'integer'],
            // Zero means "not returned"; the action ignores those rows.
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'refund_method' => ['nullable', Rule::in(Payment::methods())],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
