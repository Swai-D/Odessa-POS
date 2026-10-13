<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'purchase_id', 'user_id', 'number', 'total', 'credit_applied', 'refunded', 'refund_method',
        'reason', 'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'credit_applied' => 'integer',
            'refunded' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    /** @return HasMany<PurchaseReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
