<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Sales\Models\Sale;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A sale as reported to TRA. `gc` is the global counter (also the receipt number), `dc` the counter of the day,
 * `znum` the day as YYYYMMDD. All three must follow TRA's no-gaps, never-reused rules.
 *
 * @property Carbon $issued_at
 */
class FiscalReceipt extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $fillable = [
        'sale_id', 'gc', 'dc', 'znum', 'rctvnum', 'issued_at', 'payload', 'status',
        'attempts', 'ack_code', 'ack_message', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'gc' => 'integer',
            'dc' => 'integer',
            'znum' => 'integer',
            'attempts' => 'integer',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
