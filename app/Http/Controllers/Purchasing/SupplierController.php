<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Models\Supplier;
use App\Http\Controllers\LookupController;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupplierController extends LookupController
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function route(): string
    {
        return 'suppliers';
    }

    protected function title(): string
    {
        return 'app.menu.suppliers';
    }

    protected function scope(Builder $query): Builder
    {
        return $query->withSum('purchases as outstanding_sum', 'balance_due');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Supplier $s) => $s->name],
            ['label' => 'inventory.fields.phone', 'value' => fn (Supplier $s) => $s->phone ?? '—'],
            ['label' => 'purchases.we_owe', 'value' => fn (Supplier $s) => Money::format($s->outstanding())],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'inventory.fields.phone', 'type' => 'text'],
            ['name' => 'email', 'label' => 'pos.customers.email', 'type' => 'text'],
            ['name' => 'address', 'label' => 'inventory.fields.address', 'type' => 'text'],
            ['name' => 'is_active', 'label' => 'catalog.fields.status', 'type' => 'toggle'],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Supplier $record */
        return $record->purchases()->exists() ? __('purchases.cannot_delete_has_purchases') : null;
    }
}
