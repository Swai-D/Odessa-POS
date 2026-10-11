<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'sale_id', 'user_id', 'number', 'total', 'credit_applied', 'refunded', 'refund_method',
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

    /** @return HasMany<SaleReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
