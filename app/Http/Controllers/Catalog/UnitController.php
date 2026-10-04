<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Unit;
use App\Http\Controllers\LookupController;
use Illuminate\Database\Eloquent\Model;

class UnitController extends LookupController
{
    protected function model(): string
    {
        return Unit::class;
    }

    protected function route(): string
    {
        return 'units';
    }

    protected function title(): string
    {
        return 'app.menu.units';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Unit $u) => $u->name],
            ['label' => 'catalog.fields.short_name', 'value' => fn (Unit $u) => $u->short_name],
            ['label' => 'catalog.fields.allow_decimal', 'value' => fn (Unit $u) => $u->allow_decimal ? __('app.yes') : __('app.no')],
            ['label' => 'catalog.fields.products', 'value' => fn (Unit $u) => $u->products()->count()],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'short_name', 'label' => 'catalog.fields.short_name', 'type' => 'text', 'required' => true],
            ['name' => 'allow_decimal', 'label' => 'catalog.fields.allow_decimal', 'type' => 'toggle'],
            ['name' => 'is_active', 'label' => 'catalog.fields.status', 'type' => 'toggle'],
        ];
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInTenant('units', 'name', $record)],
            'short_name' => ['required', 'string', 'max:20'],
            'allow_decimal' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Unit $record */
        return $record->products()->exists() ? __('catalog.cannot_delete_in_use') : null;
    }
}
