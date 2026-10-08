<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Http\Controllers\LookupController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategoryController extends LookupController
{
    protected function model(): string
    {
        return ExpenseCategory::class;
    }

    protected function route(): string
    {
        return 'expense-categories';
    }

    protected function title(): string
    {
        return 'app.menu.expense_categories';
    }

    protected function scope(Builder $query): Builder
    {
        return $query->withCount('expenses');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (ExpenseCategory $c) => $c->name],
            ['label' => 'expenses.count', 'value' => fn (ExpenseCategory $c) => (int) $c->getAttribute('expenses_count')],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'is_active', 'label' => 'catalog.fields.status', 'type' => 'toggle'],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInTenant('expense_categories', 'name', $record)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var ExpenseCategory $record */
        return $record->expenses()->exists() ? __('expenses.cannot_delete_category') : null;
    }
}
