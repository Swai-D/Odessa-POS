<?php

namespace App\Domain\Settings\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the shop's history: who did what to which record and when. Written by the system only and never
 * edited. Money fields hold minor units.
 *
 * @property Carbon $created_at
 * @property array<string, array{old: mixed, new: mixed}>|null $changes
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /** Fields whose values are money in minor units, shown formatted with the shop currency. */
    public const MONEY_FIELDS = [
        'cost_price', 'selling_price', 'amount', 'total', 'subtotal', 'discount_total', 'tax_total', 'amount_paid',
        'balance_due', 'change_given', 'returned_total', 'credit_limit', 'credit_applied', 'refunded',
        'counted_cash', 'expected_cash', 'difference', 'opening_float', 'float_kept', 'sales_total',
        'cash_sales', 'cash_refunds',
    ];

    protected $fillable = [
        'user_id', 'user_name', 'ip_address', 'event', 'subject_type', 'subject_id', 'subject_label', 'changes',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
