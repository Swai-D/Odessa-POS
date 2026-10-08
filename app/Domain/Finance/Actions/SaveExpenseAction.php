<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\Expense;
use App\Models\User;
use App\Support\Money;
use App\Support\TenantSettings;

/** Records a new expense or updates one. The amount arrives as the user typed it and is stored in minor units. */
class SaveExpenseAction
{
    /** @param  array{expense_category_id: int|string, amount: float|int|string, method: string, spent_on: string, reference?: string|null, note?: string|null}  $data */
    public function handle(?Expense $expense, array $data, ?User $user): Expense
    {
        $expense ??= new Expense([
            'user_id' => $user?->getKey(),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
        ]);

        $expense->fill([
            'expense_category_id' => $data['expense_category_id'],
            'amount' => Money::toMinor($data['amount']),
            'method' => $data['method'],
            'spent_on' => $data['spent_on'],
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
        ])->save();

        return $expense;
    }
}
