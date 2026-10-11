<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Inventory\Models\Warehouse;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use Auditable, BelongsToTenant;

    public const PAID = 'paid';

    public const PARTIAL = 'partial';

    public const UNPAID = 'unpaid';

    protected $fillable = [
        'number', 'idempotency_key', 'supplier_id', 'warehouse_id', 'user_id', 'reference', 'currency',
        'total', 'amount_paid', 'balance_due', 'payment_status', 'note', 'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'amount_paid' => 'integer',
            'balance_due' => 'integer',
            'purchased_at' => 'datetime',
        ];
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return HasMany<PurchasePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
