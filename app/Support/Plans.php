<?php

namespace App\Support;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the current shop's plan allows. Outside a shop (a platform super admin) nothing is restricted.
 */
class Plans
{
    /** @var Collection<string, SubscriptionPlan>|null */
    private ?Collection $catalog = null;

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

        return $this->catalog()->has($raw) ? $raw : (string) config('plans.default');
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
        if (! in_array($feature, (array) config('plans.features'), true)) {
            return null;
        }

        foreach (SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get() as $plan) {
            $granted = $plan->features;

            if (in_array('*', $granted, true) || in_array($feature, $granted, true)) {
                return $plan->code;
            }
        }

        return null;
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
        $limits = $this->catalog()->get($this->key())->limits;

        if (array_key_exists($name, $limits)) {
            return $limits[$name] === null ? null : (int) $limits[$name];
        }

        return null;
    }

    /** @return Collection<string, SubscriptionPlan> */
    private function catalog(): Collection
    {
        return $this->catalog ??= SubscriptionPlan::query()->get()->keyBy('code');
    }

    /** @return list<string> */
    private function chain(string $key): array
    {
        $values = [];

        $values = $this->catalog()->get($this->key())->{$key};

        return array_values(array_unique($values));
    }
}
