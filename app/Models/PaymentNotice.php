<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shop telling the platform "I have paid". It is only a message for the customer-care team; it never renews
 * anything. Platform data (read by super admins and by the shop that sent it), so it deliberately has no tenant scope.
 *
 * @property Carbon $paid_on
 * @property Carbon|null $handled_at
 */
class PaymentNotice extends Model
{
    public const PENDING = 'pending';

    public const HANDLED = 'handled';

    /** How many unhandled notices one shop may have at a time, so the inbox cannot be flooded. */
    public const MAX_PENDING = 5;

    protected $fillable = [
        'tenant_id', 'user_id', 'idempotency_key', 'method', 'amount', 'currency', 'months', 'reference',
        'paid_on', 'note', 'status', 'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'months' => 'integer', 'paid_on' => 'date:Y-m-d', 'handled_at' => 'datetime'];
    }

    /** The ways a shop can say it paid: each mobile network in config/billing.php, a bank transfer, or something else. */
    public static function methods(): array
    {
        return [...array_keys((array) config('billing.mobile')), 'bank', 'other'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope('tenant');
    }
}
