<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Services\DocumentNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Returns goods from a sale: restocks them, and settles the refund value. The refund first reduces
 * what the customer still owes on the sale; only the rest is paid out, by the chosen method.
 */
class ReturnItemsAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * @param  array{items: list<array{sale_item_id: int|string, quantity: float|int|string}>, refund_method?: string|null, reason?: string|null}  $data
     */
    public function handle(Sale $sale, array $data, ?User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $user): SaleReturn {
            /** @var Sale $locked */
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());
            $items = SaleItem::query()->where('sale_id', $locked->getKey())->lockForUpdate()->get()->keyBy('id');

            $lines = $this->buildLines($items->all(), $data['items']);
            $total = array_sum(array_column($lines, 'amount'));

            $credit = min($total, $locked->balance_due);
            $refunded = $total - $credit;
            $method = $data['refund_method'] ?? null;

            if ($refunded > 0 && ! in_array($method, Payment::methods(), true)) {
                throw ValidationException::withMessages(['refund_method' => __('pos.errors.refund_method_required')]);
            }

            $return = SaleReturn::create([
                'sale_id' => $locked->getKey(),
                'user_id' => $user?->getKey(),
                'number' => $this->numbers->next('return', 'RT-'),
                'total' => $total,
                'credit_applied' => $credit,
                'refunded' => $refunded,
                'refund_method' => $refunded > 0 ? $method : null,
                'reason' => $data['reason'] ?? null,
                'returned_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = $line['item'];

                $return->items()->create([
                    'sale_item_id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'],
                ]);

                $item->update([
                    'returned_quantity' => round((float) $item->returned_quantity + $line['quantity'], 3),
                    'returned_amount' => $item->returned_amount + $line['amount'],
                ]);

                $this->restock($locked, $item, $line['quantity'], $return, $user);
            }

            $balance = $locked->balance_due - $credit;

            $locked->update([
                'returned_total' => $locked->returned_total + $total,
                'balance_due' => $balance,
                'payment_status' => match (true) {
                    $balance <= 0 => Sale::PAID,
                    $locked->amount_paid > 0 => Sale::PARTIAL,
                    default => Sale::UNPAID,
                },
            ]);

            return $return->load('items');
        });
    }

    /**
     * @param  array<int, SaleItem>  $items  the sale's items by id
     * @param  list<array{sale_item_id: int|string, quantity: float|int|string}>  $requested
     * @return list<array{item: SaleItem, quantity: float, amount: int}>
     */
    private function buildLines(array $items, array $requested): array
    {
        $quantities = [];
        foreach ($requested as $row) {
            $id = (int) $row['sale_item_id'];
            $quantities[$id] = round(($quantities[$id] ?? 0) + (float) $row['quantity'], 3);
        }

        $lines = [];
        foreach ($quantities as $id => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $item = $items[$id] ?? throw ValidationException::withMessages(['items' => __('pos.errors.return_item_invalid')]);

            if ($quantity > $item->remainingQuantity()) {
                throw ValidationException::withMessages(['items' => __('pos.errors.return_exceeds', ['product' => $item->product_name])]);
            }

            if (fmod((float) $item->quantity, 1.0) === 0.0 && fmod($quantity, 1.0) !== 0.0) {
                throw ValidationException::withMessages(['items' => __('pos.errors.return_whole_quantity', ['product' => $item->product_name])]);
            }

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'amount' => $this->refundAmount($item, $quantity)];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('pos.errors.nothing_to_return')]);
        }

        return $lines;
    }

    /** Pro-rata share of the line total; returning the last units gives the exact remainder, so nothing is lost to rounding. */
    private function refundAmount(SaleItem $item, float $quantity): int
    {
        $remainingAmount = $item->total - $item->returned_amount;

        if (abs($quantity - $item->remainingQuantity()) < 0.0005) {
            return $remainingAmount;
        }

        $share = (int) floor(round($item->total * $quantity / (float) $item->quantity, 6));

        return min($share, $remainingAmount);
    }

    private function restock(Sale $sale, SaleItem $item, float $quantity, SaleReturn $return, ?User $user): void
    {
        if ($item->product_id === null) {
            return;
        }

        $product = Product::withTrashed()->find($item->product_id);

        if ($product === null || ! $product->track_stock) {
            return;
        }

        $this->stock->move($product, $sale->warehouse, $quantity, StockMovement::SALE_RETURN, $return->number, $user, $return);
    }
}
