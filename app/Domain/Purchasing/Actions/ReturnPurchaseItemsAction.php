<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Purchasing\Models\Purchase;
use App\Domain\Purchasing\Models\PurchaseItem;
use App\Domain\Purchasing\Models\PurchaseReturn;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Services\DocumentNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sends goods back to the supplier: the stock leaves the warehouse the purchase was received into, and the
 * value first reduces what we still owe on that purchase; only the rest is money the supplier gives back.
 */
class ReturnPurchaseItemsAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * @param  array{items: list<array{purchase_item_id: int|string, quantity: float|int|string}>, refund_method?: string|null, reason?: string|null}  $data
     */
    public function handle(Purchase $purchase, array $data, ?User $user): PurchaseReturn
    {
        return DB::transaction(function () use ($purchase, $data, $user): PurchaseReturn {
            /** @var Purchase $locked */
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());
            $items = PurchaseItem::query()->where('purchase_id', $locked->getKey())->lockForUpdate()->get()->keyBy('id');

            $lines = $this->buildLines($items->all(), $data['items']);
            $total = array_sum(array_column($lines, 'amount'));

            $credit = min($total, $locked->balance_due);
            $refunded = $total - $credit;
            $method = $data['refund_method'] ?? null;

            if ($refunded > 0 && ! in_array($method, Payment::methods(), true)) {
                throw ValidationException::withMessages(['refund_method' => __('purchases.errors.refund_method_required')]);
            }

            $return = PurchaseReturn::create([
                'purchase_id' => $locked->getKey(),
                'user_id' => $user?->getKey(),
                'number' => $this->numbers->next('purchase_return', 'PR-'),
                'total' => $total,
                'credit_applied' => $credit,
                'refunded' => $refunded,
                'refund_method' => $refunded > 0 ? $method : null,
                'reason' => $data['reason'] ?? null,
                'returned_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var PurchaseItem $item */
                $item = $line['item'];

                $return->items()->create([
                    'purchase_item_id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'],
                ]);

                $item->update([
                    'returned_quantity' => round((float) $item->returned_quantity + $line['quantity'], 3),
                    'returned_amount' => $item->returned_amount + $line['amount'],
                ]);

                $this->sendOut($locked, $item, $line['quantity'], $return, $user);
            }

            $balance = $locked->balance_due - $credit;

            $locked->update([
                'returned_total' => $locked->returned_total + $total,
                'balance_due' => $balance,
                'payment_status' => match (true) {
                    $balance <= 0 => Purchase::PAID,
                    $locked->amount_paid > 0 => Purchase::PARTIAL,
                    default => Purchase::UNPAID,
                },
            ]);

            return $return->load('items');
        });
    }

    /**
     * @param  array<int, PurchaseItem>  $items  the purchase's items by id
     * @param  list<array{purchase_item_id: int|string, quantity: float|int|string}>  $requested
     * @return list<array{item: PurchaseItem, quantity: float, amount: int}>
     */
    private function buildLines(array $items, array $requested): array
    {
        $quantities = [];
        foreach ($requested as $row) {
            $id = (int) $row['purchase_item_id'];
            $quantities[$id] = round(($quantities[$id] ?? 0) + (float) $row['quantity'], 3);
        }

        $lines = [];
        foreach ($quantities as $id => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $item = $items[$id] ?? throw ValidationException::withMessages(['items' => __('purchases.errors.return_item_invalid')]);

            if ($quantity > $item->remainingQuantity()) {
                throw ValidationException::withMessages(['items' => __('purchases.errors.return_exceeds', ['product' => $item->product_name])]);
            }

            if (fmod((float) $item->quantity, 1.0) === 0.0 && fmod($quantity, 1.0) !== 0.0) {
                throw ValidationException::withMessages(['items' => __('purchases.errors.return_whole_quantity', ['product' => $item->product_name])]);
            }

            $lines[] = ['item' => $item, 'quantity' => $quantity, 'amount' => $this->creditAmount($item, $quantity)];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('purchases.errors.nothing_to_return')]);
        }

        return $lines;
    }

    /** Pro-rata share of the line total; returning the last units gives the exact remainder, so nothing is lost to rounding. */
    private function creditAmount(PurchaseItem $item, float $quantity): int
    {
        $remainingAmount = $item->total - $item->returned_amount;

        if (abs($quantity - $item->remainingQuantity()) < 0.0005) {
            return $remainingAmount;
        }

        $share = (int) floor(round($item->total * $quantity / (float) $item->quantity, 6));

        return min($share, $remainingAmount);
    }

    private function sendOut(Purchase $purchase, PurchaseItem $item, float $quantity, PurchaseReturn $return, ?User $user): void
    {
        if ($item->product_id === null) {
            return;
        }

        $product = Product::withTrashed()->find($item->product_id);

        if ($product === null || ! $product->track_stock) {
            return;
        }

        try {
            $this->stock->move($product, $purchase->warehouse, -$quantity, StockMovement::PURCHASE_RETURN, $return->number, $user, $return);
        } catch (InsufficientStockException) {
            // Goods that were already sold cannot be sent back; the whole return is refused and nothing changes.
            throw ValidationException::withMessages(['items' => __('purchases.errors.return_no_stock', ['product' => $item->product_name])]);
        }
    }
}
