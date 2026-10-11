<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use Auditable, BelongsToTenant;

    public const OPENING = 'opening';

    public const ADJUSTMENT_IN = 'adjustment_in';

    public const ADJUSTMENT_OUT = 'adjustment_out';

    public const SALE = 'sale';

    public const PURCHASE = 'purchase';

    public const SALE_RETURN = 'sale_return';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'product_id', 'warehouse_id', 'user_id', 'type', 'quantity', 'balance_after', 'reason',
        'reference_type', 'reference_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'balance_after' => 'decimal:3'];
    }

    /** Only manual adjustments are in the audit trail; sales, purchases and transfers have their own records. */
    public function shouldAudit(string $event): bool
    {
        return $event === 'created' && in_array($this->type, [self::ADJUSTMENT_IN, self::ADJUSTMENT_OUT], true);
    }

    public function auditLabel(): string
    {
        return (string) $this->product->name;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
