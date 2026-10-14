<?php

namespace App\Http\Requests;

use App\Domain\Finance\Models\BusinessStatement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the BusinessStatementPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', Rule::in(BusinessStatement::MONTH_CHOICES)],
            'locale' => ['required', Rule::in(['en', 'sw'])],
        ];
    }
}
