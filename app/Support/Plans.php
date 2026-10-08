<?php

namespace App\Support;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

/**
 * What the current shop's plan allows. Outside a shop (a platform super admin) nothing is restricted.
 */
class Plans
{
    public function __construct(private readonly ?Tenant $tenant = null) {}

    public static function current(): self
    {
        return new self(app(TenantContext::class)->get());
    }

    /** Resolved plan key: the tenant's plan, an alias of it, or the default plan. */
    public function key(): string
    {
        $raw = ($this->tenant instanceof Tenant ? (string) $this->tenant->plan : '');
        $raw = (string) config("plans.aliases.{$raw}", $raw);

        return config("plans.plans.{$raw}") !== null ? $raw : (string) config('plans.default');
    }

    public function allows(string $feature): bool
    {
        if ($this->tenant === null) {
            return true;
        }

        $granted = $this->chain('features');

        if (in_array('*', $granted, true) || in_array($feature, $granted, true)) {
            return true;
        }

        return in_array($feature, (array) data_get($this->tenant->settings, 'plan_overrides.features', []), true);
    }

    /** The smallest plan whose own features include `$feature` (ignores per-shop overrides); null when none does. */
    public static function cheapestPlanFor(string $feature): ?string
    {
        foreach (array_keys((array) config('plans.plans')) as $plan) {
            $granted = self::featuresOf((string) $plan);

            if (in_array('*', $granted, true) || in_array($feature, $granted, true)) {
                return (string) $plan;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function featuresOf(string $plan, int $depth = 0): array
    {
        $own = (array) config("plans.plans.{$plan}.features", []);
        $parent = (string) config("plans.plans.{$plan}.inherits", '');

        return array_values(array_unique($depth < 5 && $parent !== '' ? array_merge(self::featuresOf($parent, $depth + 1), $own) : $own));
    }

    /** The limit for `users`, `warehouses`...; null means unlimited. */
    public function limit(string $name): ?int
    {
        if ($this->tenant === null) {
            return null;
        }

        $overrides = (array) data_get($this->tenant->settings, 'plan_overrides.limits', []);

        if (array_key_exists($name, $overrides)) {
            return $overrides[$name] === null ? null : (int) $overrides[$name];
        }

        // The plan nearest to this one wins over the plans it inherits from.
        foreach (array_reverse($this->lineage()) as $plan) {
            $limits = (array) config("plans.plans.{$plan}.limits", []);

            if (array_key_exists($name, $limits)) {
                return $limits[$name] === null ? null : (int) $limits[$name];
            }
        }

        return null;
    }

    /** @return list<string> plan keys from the base plan down to this one */
    private function lineage(): array
    {
        $lineage = [];
        $plan = $this->key();

        while ($plan !== '' && ! in_array($plan, $lineage, true)) {
            array_unshift($lineage, $plan);
            $plan = (string) config("plans.plans.{$plan}.inherits", '');
        }

        return $lineage;
    }

    /** @return list<string> */
    private function chain(string $key): array
    {
        $values = [];

        foreach ($this->lineage() as $plan) {
            $values = array_merge($values, (array) config("plans.plans.{$plan}.{$key}", []));
        }

        return array_values(array_unique($values));
    }
}
