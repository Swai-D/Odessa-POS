<?php

namespace App\Domain\Finance\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cashier's till count at the end of a shift or day: what the system expected in the drawer against what was
 * counted. Amounts are minor units. The row is a snapshot and is never edited afterwards.
 *
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property array<string, int>|null $by_method
 */
class TillClosing extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'idempotency_key', 'currency', 'period_start', 'period_end', 'opening_float', 'cash_sales',
        'cash_refunds', 'expected_cash', 'counted_cash', 'difference', 'float_kept', 'sales_count', 'sales_total',
        'by_method', 'note',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime', 'period_end' => 'datetime',
            'opening_float' => 'integer', 'cash_sales' => 'integer', 'cash_refunds' => 'integer',
            'expected_cash' => 'integer', 'counted_cash' => 'integer', 'difference' => 'integer',
            'float_kept' => 'integer', 'sales_count' => 'integer', 'sales_total' => 'integer',
            'by_method' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
