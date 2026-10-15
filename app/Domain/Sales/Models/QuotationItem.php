<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'quotation_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_price',
        'tax_rate', 'tax_inclusive', 'gross', 'discount', 'tax', 'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'gross' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }
}
