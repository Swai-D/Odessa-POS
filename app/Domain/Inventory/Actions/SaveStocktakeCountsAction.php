<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the counted quantities typed into a sheet. An empty box means "not counted" and leaves that product alone when
 * the count is completed. Only an open count can be edited.
 */
class SaveStocktakeCountsAction
{
    /** @param  array<int|string, mixed>  $counts  stocktake item id => counted quantity (or empty) */
    public function handle(Stocktake $stocktake, array $counts): int
    {
        return DB::transaction(function () use ($stocktake, $counts): int {
            $locked = Stocktake::query()->lockForUpdate()->findOrFail($stocktake->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['counts' => __('stocktake.errors.not_open')]);
            }

            $items = StocktakeItem::query()->where('stocktake_id', $locked->getKey())->whereIn('id', array_keys($counts))->get()->keyBy('id');
            $products = Product::query()->with('unit')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
            $saved = 0;

            foreach ($counts as $id => $value) {
                $item = $items->get((int) $id);

                if ($item === null) {
                    continue;
                }

                $counted = ($value === null || trim((string) $value) === '') ? null : round((float) $value, 3);
                $product = $products->get($item->product_id);

                if ($counted !== null && $product !== null && ! ($product->is_weighed || $product->unit?->allow_decimal) && fmod($counted, 1.0) !== 0.0) {
                    throw ValidationException::withMessages(['counts.'.$id => __('stocktake.errors.whole_quantity', ['product' => $item->product_name])]);
                }

                if ($item->counted === null ? $counted !== null : $counted === null || (float) $item->counted !== $counted) {
                    $item->update(['counted' => $counted]);
                    $saved++;
                }
            }

            return $saved;
        });
    }
}
