<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property array<string, mixed>|null $callback
 */
class PaymentRequest extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    /** The mobile networks AzamPay can charge. */
    public const PROVIDERS = ['Airtel', 'Tigo', 'Halopesa', 'Azampesa', 'Mpesa'];

    protected $fillable = [
        'user_id', 'sale_id', 'idempotency_key', 'reference', 'driver', 'provider', 'phone', 'amount', 'currency', 'status',
        'provider_reference', 'mno_reference', 'failure_reason', 'callback', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'callback' => 'array', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Paid by the customer and not yet used for a sale. */
    public function isUsable(): bool
    {
        return $this->status === self::SUCCEEDED && $this->sale_id === null;
    }
}
