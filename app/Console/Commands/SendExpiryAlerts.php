<?php

namespace App\Console\Commands;

use App\Domain\Inventory\Models\StockBatch;
use App\Domain\Settings\Services\ShopNotifier;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tells each shop owner which products have stock that has expired or expires within 30 days. Runs for every shop
 * as a reviewed system operation, one shop at a time inside its own tenant context. Like the low-stock digest it is
 * sent only when the list changed since the last one, or after a week.
 */
class SendExpiryAlerts extends Command
{
    protected $signature = 'stock:expiry-alerts';

    protected $description = 'Email and SMS shop owners about stock that has expired or is about to';

    private const WARN_DAYS = 30;

    private const REPEAT_AFTER_DAYS = 7;

    public function handle(ShopNotifier $notifier, TenantContext $context): int
    {
        $sent = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->orderBy('id')->each(function (Tenant $tenant) use ($notifier, $context, &$sent): void {
            $summary = $context->run($tenant, fn (): array => $this->summary());

            if ($summary['names'] === []) {
                DB::table('expiry_alerts')->where('tenant_id', $tenant->getKey())->delete();

                return;
            }

            $names = $summary['names'];
            sort($names);
            $signature = hash('sha256', implode('|', $names).'#'.$summary['expired']);
            $last = DB::table('expiry_alerts')->where('tenant_id', $tenant->getKey())->first();

            if ($last !== null && $last->signature === $signature && now()->diffInDays($last->sent_at, true) < self::REPEAT_AFTER_DAYS) {
                return;
            }

            $notifier->expiry($tenant, count($names), $summary['expired'], array_slice($names, 0, 5));
            DB::table('expiry_alerts')->upsert(
                [['tenant_id' => $tenant->getKey(), 'signature' => $signature, 'sent_at' => now()]],
                ['tenant_id'],
                ['signature', 'sent_at'],
            );
            $sent++;
        });

        $this->info("Expiry alerts sent: {$sent}");

        return self::SUCCESS;
    }

    /** @return array{names: list<string>, expired: int} products with stock expiring soon, and how many of those are already expired */
    private function summary(): array
    {
        $batches = StockBatch::query()
            ->with('product:id,name')
            ->where('quantity_remaining', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(self::WARN_DAYS)->toDateString())
            ->whereHas('product', fn ($q) => $q->where('is_active', true)->where('track_expiry', true))
            ->get();

        $today = now()->toDateString();

        return [
            'names' => $batches->pluck('product.name')->unique()->values()->all(),
            'expired' => $batches->filter(fn (StockBatch $b): bool => $b->expiry_date->toDateString() < $today)->pluck('product_id')->unique()->count(),
        ];
    }
}
