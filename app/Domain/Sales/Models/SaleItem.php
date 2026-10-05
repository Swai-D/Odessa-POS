<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sale_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price', 'cost_price',
        'tax_rate', 'tax_inclusive', 'gross', 'discount', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'integer',
            'cost_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'gross' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
