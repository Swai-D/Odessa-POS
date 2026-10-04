<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Brand;
use App\Http\Controllers\LookupController;
use Illuminate\Database\Eloquent\Model;

class BrandController extends LookupController
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function route(): string
    {
        return 'brands';
    }

    protected function title(): string
    {
        return 'app.menu.brands';
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Brand $b) => $b->name],
            ['label' => 'catalog.fields.products', 'value' => fn (Brand $b) => $b->products()->count()],
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
            'name' => ['required', 'string', 'max:255', $this->uniqueInTenant('brands', 'name', $record)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Brand $record */
        return $record->products()->exists() ? __('catalog.cannot_delete_in_use') : null;
    }
}
