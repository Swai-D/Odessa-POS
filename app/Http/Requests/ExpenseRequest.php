<?php

namespace App\Http\Requests;

use App\Domain\Sales\Models\Payment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the Expense policy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'method' => ['required', Rule::in(Payment::methods())],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
