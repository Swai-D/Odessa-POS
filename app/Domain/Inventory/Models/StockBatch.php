<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One receipt of stock with the unit cost it was bought at. quantity_remaining is what is still on the shelf.
 *
 * @property Carbon $received_at
 * @property Carbon|null $expiry_date
 */
class StockBatch extends Model
{
    use BelongsToTenant;

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_RETURN = 'sale_return';

    public const SOURCE_TRANSFER = 'transfer_in';

    protected $fillable = [
        'product_id', 'warehouse_id', 'unit_cost', 'quantity_received', 'quantity_remaining',
        'received_at', 'source', 'reference_type', 'reference_id', 'batch_number', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'integer',
            'quantity_received' => 'decimal:3',
            'quantity_remaining' => 'decimal:3',
            'received_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
