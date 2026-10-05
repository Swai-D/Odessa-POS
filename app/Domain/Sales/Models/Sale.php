<?php

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\Warehouse;
use App\Domain\People\Models\Customer;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;

    public const PAID = 'paid';

    public const PARTIAL = 'partial';

    public const UNPAID = 'unpaid';

    protected $fillable = [
        'number', 'idempotency_key', 'customer_id', 'user_id', 'warehouse_id', 'currency',
        'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'balance_due',
        'change_given', 'payment_status', 'note', 'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'balance_due' => 'integer',
            'change_given' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
