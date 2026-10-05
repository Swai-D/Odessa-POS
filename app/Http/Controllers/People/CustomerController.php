<?php

namespace App\Http\Controllers\People;

use App\Domain\People\Models\Customer;
use App\Http\Controllers\LookupController;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerController extends LookupController
{
    protected function model(): string
    {
        return Customer::class;
    }

    protected function route(): string
    {
        return 'customers';
    }

    protected function title(): string
    {
        return 'app.menu.customers';
    }

    protected function scope(Builder $query): Builder
    {
        return $query->withSum('sales as outstanding_sum', 'balance_due');
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Customer $c) => $c->name],
            ['label' => 'inventory.fields.phone', 'value' => fn (Customer $c) => $c->phone ?? '—'],
            ['label' => 'pos.customers.outstanding', 'value' => fn (Customer $c) => Money::format($c->outstanding())],
            ['label' => 'pos.customers.credit_limit', 'value' => fn (Customer $c) => $c->credit_limit === null ? '—' : Money::format($c->credit_limit)],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'phone', 'label' => 'inventory.fields.phone', 'type' => 'text'],
            ['name' => 'email', 'label' => 'pos.customers.email', 'type' => 'text'],
            ['name' => 'address', 'label' => 'inventory.fields.address', 'type' => 'text'],
            ['name' => 'credit_limit_input', 'label' => 'pos.customers.credit_limit', 'type' => 'text'],
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
            'credit_limit_input' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        $limit = $data['credit_limit_input'] ?? null;
        unset($data['credit_limit_input']);
        $data['credit_limit'] = $limit === null || $limit === '' ? null : Money::toMinor($limit);

        return $data;
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Customer $record */
        return $record->sales()->exists() ? __('pos.customers.cannot_delete_has_sales') : null;
    }
}
