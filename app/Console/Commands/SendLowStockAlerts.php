<?php

namespace App\Console\Commands;

use App\Domain\Sales\Services\DashboardSummary;
use App\Domain\Settings\Services\ShopNotifier;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tells each shop owner which products are at or below their alert quantity. Runs for every shop as a reviewed
 * system operation, one shop at a time inside its own tenant context. A digest is sent only when the list changed
 * since the last one, or after a week, so a shop is not nagged every morning about the same items.
 */
class SendLowStockAlerts extends Command
{
    protected $signature = 'stock:low-alerts';

    protected $description = 'Email and SMS shop owners about products that are running low';

    private const REPEAT_AFTER_DAYS = 7;

    public function handle(ShopNotifier $notifier, TenantContext $context): int
    {
        $sent = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->orderBy('id')->each(function (Tenant $tenant) use ($notifier, $context, &$sent): void {
            $summary = $context->run($tenant, function (): array {
                $products = (new DashboardSummary)->lowStock(200);

                return ['count' => $products->count(), 'names' => $products->pluck('name')->all()];
            });

            if ($summary['count'] === 0) {
                DB::table('low_stock_alerts')->where('tenant_id', $tenant->getKey())->delete();

                return;
            }

            $names = $summary['names'];
            sort($names);
            $signature = hash('sha256', implode('|', $names));
            $last = DB::table('low_stock_alerts')->where('tenant_id', $tenant->getKey())->first();

            if ($last !== null && $last->signature === $signature && now()->diffInDays($last->sent_at, true) < self::REPEAT_AFTER_DAYS) {
                return;
            }

            $notifier->lowStock($tenant, $summary['count'], array_slice($names, 0, 5));
            DB::table('low_stock_alerts')->upsert(
                [['tenant_id' => $tenant->getKey(), 'signature' => $signature, 'sent_at' => now()]],
                ['tenant_id'],
                ['signature', 'sent_at'],
            );
            $sent++;
        });

        $this->info("Low-stock alerts sent: {$sent}");

        return self::SUCCESS;
    }
}
