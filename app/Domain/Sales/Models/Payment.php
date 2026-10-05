<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToTenant;

    public const CASH = 'cash';

    public const CARD = 'card';

    public const MOBILE_MONEY = 'mobile_money';

    public const CHEQUE = 'cheque';

    public const BANK_TRANSFER = 'bank_transfer';

    /** @return list<string> */
    public static function methods(): array
    {
        return [self::CASH, self::CARD, self::MOBILE_MONEY, self::CHEQUE, self::BANK_TRANSFER];
    }

    protected $fillable = ['sale_id', 'user_id', 'method', 'amount', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
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
