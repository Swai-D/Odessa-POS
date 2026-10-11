<?php

namespace App\Domain\Settings\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantSettings;

/**
 * The "get your shop ready" checklist shown to the Owner on the dashboard.
 * Every step is worked out from real data, so nothing is ticked by hand.
 */
class OnboardingChecklist
{
    public function dismissed(): bool
    {
        return (bool) (new TenantSettings)->get('onboarding_dismissed', false);
    }

    /**
     * @return list<array{key: string, done: bool, route: string, help: string}>
     */
    public function steps(): array
    {
        return [
            ['key' => 'details', 'done' => (string) (new TenantSettings)->get('business_name', '') !== '', 'route' => 'settings.index', 'help' => 'getting-started/first-steps'],
            ['key' => 'products', 'done' => Product::query()->exists(), 'route' => 'products.index', 'help' => 'products/add-a-product'],
            ['key' => 'stock', 'done' => ProductStock::query()->where('quantity', '>', 0)->exists(), 'route' => 'stock.index', 'help' => 'products/import-export'],
            ['key' => 'staff', 'done' => User::query()->count() > 1, 'route' => 'users.index', 'help' => 'settings/users-and-roles'],
            ['key' => 'sale', 'done' => Sale::query()->exists(), 'route' => 'pos.index', 'help' => 'selling/make-a-sale'],
        ];
    }

    /**
     * Null when the card should not show: dismissed, or everything is done.
     *
     * @return array{steps: list<array{key: string, done: bool, route: string, help: string}>, done: int, total: int, percent: int}|null
     */
    public function card(): ?array
    {
        if ($this->dismissed()) {
            return null;
        }

        $steps = $this->steps();
        $done = count(array_filter($steps, fn (array $step): bool => $step['done']));
        $total = count($steps);

        if ($done === $total) {
            return null;
        }

        return ['steps' => $steps, 'done' => $done, 'total' => $total, 'percent' => (int) round($done / $total * 100)];
    }

    public function dismiss(Tenant $tenant): void
    {
        /** @var array<string, mixed> $settings */
        $settings = $tenant->settings ?? [];
        $settings['onboarding_dismissed'] = true;
        $tenant->update(['settings' => $settings]);
    }
}
