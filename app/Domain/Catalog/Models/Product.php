<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Inventory\Models\ProductStock;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_SERVICE = 'service';

    protected $fillable = [
        'category_id', 'brand_id', 'unit_id', 'type', 'name', 'sku', 'barcode', 'description',
        'cost_price', 'selling_price', 'tax_rate', 'tax_inclusive', 'track_stock', 'alert_quantity',
        'is_weighed', 'track_batch', 'track_expiry', 'is_active', 'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'alert_quantity' => 'decimal:3',
            'tax_inclusive' => 'boolean',
            'track_stock' => 'boolean',
            'is_weighed' => 'boolean',
            'track_batch' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
            'custom_fields' => 'array',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    /** Total quantity across warehouses (needs the `stocks_sum_quantity` aggregate when listing). */
    public function totalQuantity(): float
    {
        return (float) ($this->stocks_sum_quantity ?? $this->stocks()->sum('quantity'));
    }

    public function isLowStock(): bool
    {
        return $this->track_stock
            && (float) $this->alert_quantity > 0
            && $this->totalQuantity() <= (float) $this->alert_quantity;
    }
}
