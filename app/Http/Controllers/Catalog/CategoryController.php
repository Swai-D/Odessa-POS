<?php

namespace App\Http\Controllers\Catalog;

use App\Domain\Catalog\Models\Category;
use App\Http\Controllers\LookupController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends LookupController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function route(): string
    {
        return 'categories';
    }

    protected function title(): string
    {
        return 'app.menu.categories';
    }

    protected function with(): array
    {
        return ['parent'];
    }

    protected function columns(): array
    {
        return [
            ['label' => 'catalog.fields.name', 'value' => fn (Category $c) => $c->name],
            ['label' => 'catalog.fields.slug', 'value' => fn (Category $c) => $c->slug],
            ['label' => 'catalog.fields.parent', 'value' => fn (Category $c) => $c->parent->name ?? '—'],
            ['label' => 'catalog.fields.products', 'value' => fn (Category $c) => $c->products()->count()],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'catalog.fields.name', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'label' => 'catalog.fields.slug', 'type' => 'text'],
            [
                'name' => 'parent_id',
                'label' => 'catalog.fields.parent',
                'type' => 'select',
                'options' => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
            ],
            ['name' => 'is_active', 'label' => 'catalog.fields.status', 'type' => 'toggle'],
        ];
    }

    protected function rules(?Model $record): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', $this->uniqueInTenant('categories', 'slug', $record)],
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
                ...($record ? [Rule::notIn([$record->getKey()])] : []),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        $slug = $data['slug'] ?? null;
        $data['slug'] = $slug !== null && $slug !== '' ? Str::slug($slug) : Str::slug($data['name']);

        // Auto-generated slugs must stay unique inside the tenant.
        $base = $data['slug'];
        $suffix = 2;
        while (Category::query()->where('slug', $data['slug'])->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists()) {
            $data['slug'] = $base.'-'.$suffix++;
        }

        return $data;
    }

    protected function deletionBlockedReason(Model $record): ?string
    {
        /** @var Category $record */
        if ($record->products()->exists()) {
            return __('catalog.cannot_delete_in_use');
        }

        return Category::query()->where('parent_id', $record->getKey())->exists()
            ? __('catalog.cannot_delete_has_children')
            : null;
    }
}
