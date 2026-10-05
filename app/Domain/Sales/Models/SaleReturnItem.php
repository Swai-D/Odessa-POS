<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['sale_return_id', 'sale_item_id', 'product_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount' => 'integer'];
    }

    /** @return BelongsTo<SaleItem, $this> */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
