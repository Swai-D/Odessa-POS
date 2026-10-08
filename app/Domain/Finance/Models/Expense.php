<?php

namespace App\Domain\Finance\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money a shop spent that is not stock (rent, electricity, wages, transport...). Amount is in minor units.
 *
 * @property Carbon $spent_on
 */
class Expense extends Model
{
    use BelongsToTenant;

    protected $fillable = ['expense_category_id', 'user_id', 'amount', 'currency', 'method', 'reference', 'note', 'spent_on'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'spent_on' => 'date'];
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
