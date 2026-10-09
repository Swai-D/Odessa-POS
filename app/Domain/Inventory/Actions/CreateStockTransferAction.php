<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Domain\Sales\Services\DocumentNumber;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves stock between two of the shop's warehouses. Each line takes stock out of the source and puts the same
 * quantity into the destination through the stock ledger, all in one transaction: if any line cannot be covered
 * by the source, nothing moves. The client's idempotency key makes a double submit harmless.
 */
class CreateStockTransferAction
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * @param  array{idempotency_key: string, from_warehouse_id: int|string, to_warehouse_id: int|string, note?: string|null, items: list<array{product_id: int|string, quantity: float|int|string}>}  $data
     * @return array{transfer: StockTransfer, created: bool}
     */
    public function handle(array $data, ?User $user): array
    {
        if ($existing = $this->findExisting($data['idempotency_key'])) {
            return ['transfer' => $existing, 'created' => false];
        }

        try {
            $transfer = DB::transaction(fn (): StockTransfer => $this->create($data, $user));
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $this->findExisting($data['idempotency_key'])) {
                return ['transfer' => $existing, 'created' => false];
            }

            throw $e;
        }

        return ['transfer' => $transfer, 'created' => true];
    }

    private function findExisting(string $key): ?StockTransfer
    {
        return StockTransfer::query()->where('idempotency_key', $key)->first();
    }

    /** @param  array<string, mixed>  $data */
    private function create(array $data, ?User $user): StockTransfer
    {
        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            throw ValidationException::withMessages(['to_warehouse_id' => __('transfers.errors.same_warehouse')]);
        }

        $from = Warehouse::query()->where('is_active', true)->find($data['from_warehouse_id']);
        $to = Warehouse::query()->where('is_active', true)->find($data['to_warehouse_id']);

        if ($from === null || $to === null) {
            throw ValidationException::withMessages(['from_warehouse_id' => __('transfers.errors.warehouse_unavailable')]);
        }

        // Merge repeated products so each gets one line and one pair of movements.
        $lines = [];
        foreach ($data['items'] as $row) {
            $id = (int) $row['product_id'];
            $lines[$id] = round(($lines[$id] ?? 0) + (float) $row['quantity'], 3);
        }

        // Ordered by id so two transfers touching the same products lock rows in the same order.
        $products = Product::query()->with('unit')->where('is_active', true)->where('track_stock', true)
            ->whereIn('id', array_keys($lines))->orderBy('id')->get();

        if ($products->count() !== count($lines)) {
            throw ValidationException::withMessages(['items' => __('transfers.errors.product_unavailable')]);
        }

        foreach ($products as $product) {
            if (! ($product->is_weighed || $product->unit?->allow_decimal) && fmod($lines[$product->getKey()], 1.0) !== 0.0) {
                throw ValidationException::withMessages(['items' => __('transfers.errors.whole_quantity', ['product' => $product->name])]);
            }
        }

        $transfer = StockTransfer::create([
            'number' => $this->numbers->next('stock_transfer', 'TR-'),
            'idempotency_key' => $data['idempotency_key'],
            'from_warehouse_id' => $from->getKey(),
            'to_warehouse_id' => $to->getKey(),
            'user_id' => $user?->getKey(),
            'note' => $data['note'] ?? null,
            'transferred_at' => now(),
        ]);

        foreach ($products as $product) {
            $quantity = $lines[$product->getKey()];

            $transfer->items()->create([
                'product_id' => $product->getKey(),
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
            ]);

            try {
                $this->stock->move($product, $from, -$quantity, StockMovement::TRANSFER_OUT, $transfer->number, $user, $transfer);
            } catch (InsufficientStockException $e) {
                throw ValidationException::withMessages(['items' => __('transfers.errors.not_enough', ['product' => $product->name, 'warehouse' => $from->name])]);
            }

            $this->stock->move($product, $to, $quantity, StockMovement::TRANSFER_IN, $transfer->number, $user, $transfer);
        }

        return $transfer->load(['items', 'fromWarehouse', 'toWarehouse']);
    }
}
