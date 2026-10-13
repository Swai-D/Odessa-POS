<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'product_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount' => 'integer'];
    }

    /** @return BelongsTo<PurchaseItem, $this> */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
