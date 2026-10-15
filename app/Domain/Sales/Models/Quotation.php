<?php

namespace App\Domain\Sales\Models;

use App\Domain\People\Models\Customer;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An offer to a customer. It reserves no stock and takes no money; accepting it and converting it makes a real sale.
 *
 * @property Carbon $quoted_at
 * @property Carbon|null $valid_until
 */
class Quotation extends Model
{
    use BelongsToTenant;

    public const DRAFT = 'draft';

    public const SENT = 'sent';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const CONVERTED = 'converted';

    protected $fillable = [
        'number', 'idempotency_key', 'customer_id', 'customer_name', 'user_id', 'sale_id', 'currency',
        'subtotal', 'discount_total', 'tax_total', 'total', 'status', 'valid_until', 'note', 'quoted_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'valid_until' => 'date',
            'quoted_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public static function manualStatuses(): array
    {
        return [self::SENT, self::ACCEPTED, self::DECLINED];
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
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

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Past its valid-until date and still waiting for an answer. Shown as a label only; the stored status does not change. */
    public function isExpired(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SENT], true)
            && $this->valid_until !== null
            && $this->valid_until->endOfDay()->isPast();
    }

    public function canConvert(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SENT, self::ACCEPTED], true) && ! $this->isExpired();
    }

    public function displayName(): string
    {
        return $this->customer?->name ?? $this->customer_name ?? '';
    }
}
