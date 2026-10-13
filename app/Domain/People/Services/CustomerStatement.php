<?php

namespace App\Domain\People\Services;

use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * A customer's account statement: their sales in a period with what was returned, paid and is still owed.
 * Amounts are integer minor units. The balance is what each sale's own balance says, so the statement can
 * never disagree with the sale pages.
 */
class CustomerStatement
{
    /**
     * @return array{sales: Collection<int, Sale>, total: int, returned: int, paid: int, balance: int}
     */
    public function handle(Customer $customer, ?Carbon $from = null, ?Carbon $to = null, bool $openOnly = false): array
    {
        $query = Sale::query()->where('customer_id', $customer->getKey())->with('payments')->orderBy('sold_at')->orderBy('id');

        if ($from !== null) {
            $query->where('sold_at', '>=', $from->copy()->startOfDay());
        }

        if ($to !== null) {
            $query->where('sold_at', '<=', $to->copy()->endOfDay());
        }

        if ($openOnly) {
            $query->where('balance_due', '>', 0);
        }

        $sales = $query->get();

        return [
            'sales' => $sales,
            'total' => (int) $sales->sum('total'),
            'returned' => (int) $sales->sum('returned_total'),
            'paid' => (int) $sales->sum('amount_paid'),
            'balance' => (int) $sales->sum('balance_due'),
        ];
    }
}
