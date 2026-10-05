<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\DocumentNumber;
use App\Domain\Sales\Services\SaleCalculator;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into a persisted sale. The server is the only authority for prices, tax and totals:
 * the browser only sends product ids and quantities. Everything happens in one transaction, and the
 * client's idempotency key makes a retry or a double click return the original sale.
 */
class CheckoutAction
{
    public function __construct(
        private readonly SaleCalculator $calculator,
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated CheckoutRequest data
     * @return array{sale: Sale, created: bool}
     */
    public function handle(array $data, ?User $user): array
    {
        if ($existing = $this->findExisting($data['idempotency_key'])) {
            return ['sale' => $existing, 'created' => false];
        }

        try {
            $sale = DB::transaction(fn (): Sale => $this->create($data, $user));
        } catch (UniqueConstraintViolationException $e) {
            // Two identical requests raced; the other one won. Hand back its sale.
            if ($existing = $this->findExisting($data['idempotency_key'])) {
                return ['sale' => $existing, 'created' => false];
            }

            throw $e;
        }

        return ['sale' => $sale, 'created' => true];
    }

    private function findExisting(string $key): ?Sale
    {
        return Sale::query()->where('idempotency_key', $key)->first();
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data, ?User $user): Sale
    {
        $warehouse = Warehouse::query()->where('is_active', true)->findOrFail($data['warehouse_id']);
        $quantities = $this->mergeQuantities($data['items']);
        $products = Product::query()
            ->with('unit')
            ->where('is_active', true)
            ->whereIn('id', array_keys($quantities))
            ->orderBy('id')
            ->get();

        if ($products->count() !== count($quantities)) {
            throw ValidationException::withMessages(['items' => __('pos.errors.product_unavailable')]);
        }

        $lines = [];
        foreach ($products as $product) {
            $quantity = $quantities[$product->getKey()];
            $allowsDecimals = $product->is_weighed || $product->unit?->allow_decimal;

            if (! $allowsDecimals && fmod($quantity, 1.0) !== 0.0) {
                throw ValidationException::withMessages(['items' => __('pos.errors.whole_quantity', ['product' => $product->name])]);
            }

            $lines[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $product->selling_price,
                'tax_rate' => (float) $product->tax_rate,
                'tax_inclusive' => $product->tax_inclusive,
            ];
        }

        $totals = $this->calculator->calculate($lines, $data['discount'] ?? null);

        if ($totals['total'] <= 0) {
            throw ValidationException::withMessages(['items' => __('pos.errors.nothing_to_charge')]);
        }

        $customer = isset($data['customer_id'])
            ? Customer::query()->where('is_active', true)->lockForUpdate()->findOrFail($data['customer_id'])
            : null;

        $settlement = $this->settle($totals['total'], $data['payments'] ?? [], $customer);

        $sale = Sale::create([
            'number' => $this->numbers->next('sale', 'SL-'),
            'idempotency_key' => $data['idempotency_key'],
            'customer_id' => $customer?->getKey(),
            'user_id' => $user?->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'amount_paid' => $settlement['paid'],
            'balance_due' => $settlement['balance'],
            'change_given' => $settlement['change'],
            'payment_status' => $settlement['status'],
            'note' => $data['note'] ?? null,
            'sold_at' => now(),
        ]);

        foreach ($lines as $i => $line) {
            /** @var Product $product */
            $product = $line['product'];

            $sale->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'cost_price' => $product->cost_price,
                'tax_rate' => $line['tax_rate'],
                'tax_inclusive' => $line['tax_inclusive'],
                'gross' => $totals['lines'][$i]['gross'],
                'discount' => $totals['lines'][$i]['discount'],
                'tax' => $totals['lines'][$i]['tax'],
                'total' => $totals['lines'][$i]['total'],
            ]);

            if ($product->track_stock) {
                try {
                    $this->stock->move(
                        $product,
                        $warehouse,
                        -$line['quantity'],
                        StockMovement::SALE,
                        $sale->number,
                        $user,
                        $sale,
                    );
                } catch (InsufficientStockException $e) {
                    throw ValidationException::withMessages(['items' => $e->getMessage()]);
                }
            }
        }

        foreach ($settlement['payments'] as $payment) {
            $sale->payments()->create([
                'user_id' => $user?->getKey(),
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'reference' => $payment['reference'] ?? null,
                'paid_at' => now(),
            ]);
        }

        return $sale->load(['items', 'payments', 'customer']);
    }

    /**
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $items
     * @return array<int, float> product id => total quantity
     */
    private function mergeQuantities(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $quantities[$id] = round(($quantities[$id] ?? 0) + (float) $item['quantity'], 3);
        }

        return $quantities;
    }

    /**
     * Decide what is paid now, what is owed and what change is returned.
     * Cash may exceed the amount due (the surplus is change); every other method may not.
     *
     * @param  list<array{method: string, amount: int|string, reference?: string|null}>  $payments
     * @return array{paid: int, balance: int, change: int, status: string, payments: list<array{method: string, amount: int, reference: string|null}>}
     */
    private function settle(int $total, array $payments, ?Customer $customer): array
    {
        $cash = 0;
        $cashReference = null;
        $other = [];
        $otherTotal = 0;

        foreach ($payments as $payment) {
            $amount = (int) $payment['amount'];

            if ($payment['method'] === Payment::CASH) {
                $cash += $amount;
                $cashReference ??= $payment['reference'] ?? null;
            } else {
                $other[] = ['method' => $payment['method'], 'amount' => $amount, 'reference' => $payment['reference'] ?? null];
                $otherTotal += $amount;
            }
        }

        if ($otherTotal > $total) {
            throw ValidationException::withMessages(['payments' => __('pos.errors.overpaid_non_cash')]);
        }

        $dueForCash = $total - $otherTotal;
        $cashApplied = min($cash, $dueForCash);
        $change = $cash - $cashApplied;
        $paid = $otherTotal + $cashApplied;
        $balance = $total - $paid;

        if ($balance > 0) {
            if (! $customer) {
                throw ValidationException::withMessages(['payments' => __('pos.errors.customer_required_for_credit')]);
            }

            if ($customer->credit_limit !== null && $customer->outstanding() + $balance > $customer->credit_limit) {
                throw ValidationException::withMessages(['payments' => __('pos.errors.credit_limit_exceeded')]);
            }
        }

        $recorded = $other;
        if ($cashApplied > 0) {
            array_unshift($recorded, ['method' => Payment::CASH, 'amount' => $cashApplied, 'reference' => $cashReference]);
        }

        return [
            'paid' => $paid,
            'balance' => $balance,
            'change' => $change,
            'status' => $balance === 0 ? Sale::PAID : ($paid > 0 ? Sale::PARTIAL : Sale::UNPAID),
            'payments' => $recorded,
        ];
    }
}
