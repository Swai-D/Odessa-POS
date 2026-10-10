<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A subscription payment from a shop to the platform. Platform data (read by super admins only), so it
 * deliberately has no tenant scope.
 *
 * @property Carbon $paid_on
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
class TenantPayment extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'idempotency_key', 'plan', 'plan_name', 'plan_price_amount', 'discount_amount', 'discount_reason', 'amount', 'currency', 'method',
        'reference', 'note', 'months', 'paid_on', 'period_start', 'period_end',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'plan_price_amount' => 'integer', 'discount_amount' => 'integer', 'months' => 'integer',
            'paid_on' => 'date:Y-m-d', 'period_start' => 'date:Y-m-d', 'period_end' => 'date:Y-m-d',
        ];
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
