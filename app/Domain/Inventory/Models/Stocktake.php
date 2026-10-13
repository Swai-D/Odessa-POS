<?php

namespace App\Domain\Inventory\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A physical stock count of one warehouse. It starts as a sheet of what the system expects, people write in what
 * they actually counted, and completing it posts the differences to stock through the ledger as adjustments.
 *
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 */
class Stocktake extends Model
{
    use Auditable, BelongsToTenant;

    public const OPEN = 'open';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['number', 'idempotency_key', 'warehouse_id', 'user_id', 'status', 'note', 'started_at', 'completed_at', 'completed_by'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** @return HasMany<StocktakeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope('tenant');
    }
}
