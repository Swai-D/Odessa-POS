<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purchase_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_cost', 'total', 'returned_quantity', 'returned_amount'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'integer',
            'total' => 'integer',
            'returned_quantity' => 'decimal:3',
            'returned_amount' => 'integer',
        ];
    }

    /** Quantity that can still be returned to the supplier. */
    public function remainingQuantity(): float
    {
        return round((float) $this->quantity - (float) $this->returned_quantity, 3);
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
