<?php

namespace App\Http\Requests;

use App\Domain\Catalog\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is done with the ProductPolicy in the controller.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->get()?->getKey();
        /** @var Product|null $product */
        $product = $this->route('product') instanceof Product ? $this->route('product') : null;
        $inTenant = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenantId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', $tenantId)->ignore($product?->getKey()),
            ],
            'barcode' => ['nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in([Product::TYPE_STANDARD, Product::TYPE_SERVICE])],
            'category_id' => ['nullable', $inTenant('categories')],
            'brand_id' => ['nullable', $inTenant('brands')],
            'unit_id' => ['nullable', $inTenant('units')],
            'description' => ['nullable', 'string', 'max:5000'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'tax_inclusive' => ['required', 'boolean'],
            'track_stock' => ['required', 'boolean'],
            'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'is_weighed' => ['required', 'boolean'],
            'track_batch' => ['required', 'boolean'],
            'track_expiry' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            // Opening stock is only accepted when creating a product.
            'warehouse_id' => [
                Rule::requiredIf(fn () => $product === null && (float) $this->input('opening_quantity') > 0),
                'nullable',
                $inTenant('warehouses'),
            ],
            'opening_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
