<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\Warehouse;
use App\Http\Controllers\LookupController;
use Illuminate\Database\Eloquent\Model;

class WarehouseController extends LookupController
{
    protected function model(): string
    {
        return Warehouse::class;
    }

    protected function route(): string
    {
        return 'warehouses';
    }

    protected function title(): string
    {
        return 'app.menu.warehouses';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Warehouse $w) => $w->name],
            ['label' => 'inventory.fields.code', 'value' => fn (Warehouse $w) => $w->code],
            ['label' => 'inventory.fields.phone', 'value' => fn (Warehouse $w) => $w->phone ?? '—'],
            ['label' => 'inventory.fields.default', 'value' => fn (Warehouse $w) => $w->is_default ? __('app.yes') : __('app.no')],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'code', 'label' => 'inventory.fields.code', 'type' => 'text', 'required' => true],
            ['name' => 'address', 'label' => 'inventory.fields.address', 'type' => 'text'],
            ['name' => 'phone', 'label' => 'inventory.fields.phone', 'type' => 'text'],
            ['name' => 'is_default', 'label' => 'inventory.fields.default', 'type' => 'toggle'],
            ['name' => 'is_active', 'label' => 'catalog.fields.status', 'type' => 'toggle'],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', $this->uniqueInTenant('warehouses', 'code', $record)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        // Only one default warehouse per tenant.
        if ($data['is_default']) {
            Warehouse::query()
                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                ->update(['is_default' => false]);
        }

        return $data;
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Warehouse $record */
        return $record->movements()->exists() ? __('inventory.cannot_delete_has_stock') : null;
    }
}
