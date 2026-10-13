<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['stocktake_id', 'product_id', 'product_name', 'sku', 'expected', 'counted', 'adjusted'];

    protected function casts(): array
    {
        return ['expected' => 'decimal:3', 'counted' => 'decimal:3', 'adjusted' => 'decimal:3'];
    }

    /** What was counted minus what was expected; null while the line is not counted. */
    public function difference(): ?float
    {
        return $this->counted === null ? null : round((float) $this->counted - (float) $this->expected, 3);
    }

    /** @return BelongsTo<Stocktake, $this> */
    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
