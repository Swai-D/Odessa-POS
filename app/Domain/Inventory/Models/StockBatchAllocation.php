<?php

namespace App\Domain\Inventory\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The part of a stock movement that was taken from one batch, at that batch's unit cost. */
class StockBatchAllocation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['stock_movement_id', 'stock_batch_id', 'quantity', 'unit_cost', 'returned_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'integer',
            'returned_quantity' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** @return BelongsTo<StockBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }
}
