<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Models\User;
use App\Support\Money;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\DB;

class SaveProductAction
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Create or update a product from validated request data.
     * Prices arrive in major units (e.g. 1500.50) and are stored as integer minor units.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Product $product, ?User $user): Product
    {
        return DB::transaction(function () use ($data, $product, $user): Product {
            $openingQuantity = (float) ($data['opening_quantity'] ?? 0);
            $warehouseId = $data['warehouse_id'] ?? null;
            unset($data['opening_quantity'], $data['warehouse_id']);

            $data['cost_price'] = Money::toMinor($data['cost_price']);
            $data['selling_price'] = Money::toMinor($data['selling_price']);
            $data['alert_quantity'] = $data['alert_quantity'] ?? 0;

            // Optional behaviours can only be switched on when the tenant has enabled the feature.
            $settings = new TenantSettings;
            foreach (['is_weighed' => 'weighed_products', 'track_batch' => 'batch_tracking', 'track_expiry' => 'expiry_tracking'] as $field => $feature) {
                $data[$field] = (bool) ($data[$field] ?? false) && $settings->feature($feature);
            }

            // Services never hold stock.
            if ($data['type'] === Product::TYPE_SERVICE) {
                $data['track_stock'] = false;
                $data['alert_quantity'] = 0;
            }

            if ($product) {
                $product->update($data);

                return $product;
            }

            $product = Product::create($data);

            if ($product->track_stock && $openingQuantity > 0 && $warehouseId) {
                $this->stock->move(
                    $product,
                    Warehouse::query()->findOrFail($warehouseId),
                    $openingQuantity,
                    StockMovement::OPENING,
                    __('inventory.opening_stock'),
                    $user,
                );
            }

            return $product;
        });
    }
}
