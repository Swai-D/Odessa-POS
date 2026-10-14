<?php

namespace App\Console\Commands;

use App\Domain\Settings\Services\ShopNotifier;
use App\Models\Tenant;
use App\Support\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tells shop owners their subscription ends soon, today, or has ended (inside the grace period).
 * Runs for every shop as a reviewed system operation. Each reminder is recorded once, so running twice is safe.
 */
class SendSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:remind';

    protected $description = 'Email and SMS shop owners whose subscription ends soon or has just ended';

    public function handle(ShopNotifier $notifier): int
    {
        $days = array_map('intval', (array) config('notifications.reminder_days'));
        $sent = 0;

        Tenant::query()->whereIn('status', ['trial', 'active'])->orderBy('id')->each(function (Tenant $tenant) use ($days, $notifier, &$sent): void {
            $subscription = new Subscription($tenant);
            $deadline = $subscription->deadline();
            $left = $subscription->daysLeft();

            if ($deadline === null || $left === null) {
                return;
            }

            // Days to remind on: the configured ones, plus the last day of grace (-grace_days) as a final warning.
            $kind = $left > 0 ? "before_{$left}" : ($left === 0 ? 'today' : 'after_'.abs($left));
            $wanted = in_array($left, $days, true) || $left === -((int) config('plans.grace_days'));

            if (! $wanted || $this->alreadySent($tenant, $kind, $deadline)) {
                return;
            }

            $notifier->subscriptionReminder($tenant, $left, $deadline);
            $sent++;
        });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }

    private function alreadySent(Tenant $tenant, string $kind, CarbonInterface $deadline): bool
    {
        // insertOrIgnore returns 0 when the unique row already exists, meaning this reminder was sent before.
        return DB::table('subscription_reminders')->insertOrIgnore([
            'tenant_id' => $tenant->getKey(),
            'kind' => $kind,
            'deadline' => $deadline->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 0;
    }
}
