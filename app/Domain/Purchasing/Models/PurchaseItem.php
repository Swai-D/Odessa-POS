<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purchase_id', 'product_id', 'product_name', 'sku', 'quantity', 'unit_cost', 'total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'integer', 'total' => 'integer'];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
