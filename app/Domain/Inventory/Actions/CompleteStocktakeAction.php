<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use App\Domain\Inventory\Services\InsufficientStockException;
use App\Domain\Inventory\Services\StockService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Finishes a count: for every counted product that differs from what was expected, the difference is posted to stock as
 * an adjustment in the ledger (so it shows in stock adjustments and the audit trail). Products left empty are not
 * touched. It is all or nothing: if a shortage cannot be posted, nothing changes. Completing twice does nothing.
 *
 * The difference is counted minus what was expected when the sheet was opened, so sales made while counting stay
 * correct as long as the shelves were counted at the time the sheet was opened.
 */
class CompleteStocktakeAction
{
    public function __construct(private readonly StockService $stock) {}

    /** @return array{adjusted: int, surplus: float, shortage: float} */
    public function handle(Stocktake $stocktake, ?User $user): array
    {
        return DB::transaction(function () use ($stocktake, $user): array {
            $locked = Stocktake::query()->with('warehouse')->lockForUpdate()->findOrFail($stocktake->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['counts' => __('stocktake.errors.not_open')]);
            }

            $adjusted = 0;
            $surplus = 0.0;
            $shortage = 0.0;
            $items = StocktakeItem::query()->where('stocktake_id', $locked->getKey())->whereNotNull('counted')->orderBy('product_id')->get();

            foreach ($items as $item) {
                $difference = (float) $item->difference();

                if ($difference === 0.0) {
                    $item->update(['adjusted' => 0]);

                    continue;
                }

                $product = Product::withTrashed()->findOrFail($item->product_id);

                try {
                    $this->stock->move(
                        $product,
                        $locked->warehouse,
                        $difference,
                        $difference > 0 ? StockMovement::ADJUSTMENT_IN : StockMovement::ADJUSTMENT_OUT,
                        __('stocktake.reason', ['number' => $locked->number]),
                        $user,
                        $locked,
                    );
                } catch (InsufficientStockException $e) {
                    throw ValidationException::withMessages(['counts' => __('stocktake.errors.cannot_post', ['product' => $item->product_name])]);
                }

                $item->update(['adjusted' => $difference]);
                $adjusted++;
                $difference > 0 ? $surplus += $difference : $shortage += abs($difference);
            }

            $locked->update(['status' => Stocktake::COMPLETED, 'completed_at' => now(), 'completed_by' => $user?->getKey()]);

            return ['adjusted' => $adjusted, 'surplus' => round($surplus, 3), 'shortage' => round($shortage, 3)];
        });
    }
}
