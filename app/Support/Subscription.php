<?php

namespace App\Support;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Where a shop stands with its subscription. Nothing here ever deletes data; the worst state is read-only.
 *
 *  active    - paid (or no deadline set), everything works
 *  grace     - the deadline has passed but the grace period has not; everything works, with a warning
 *  readonly  - grace is over; the shop can read and print but not sell or change anything
 *  suspended - switched off by the platform admin
 */
class Subscription
{
    public const ACTIVE = 'active';

    public const GRACE = 'grace';

    public const READONLY = 'readonly';

    public const SUSPENDED = 'suspended';

    public function __construct(private readonly ?Tenant $tenant, private readonly ?CarbonInterface $now = null) {}

    public static function current(): self
    {
        return new self(app(TenantContext::class)->get());
    }

    /** The day the shop must have paid by: the paid-until date, or the end of a trial. */
    public function deadline(): ?CarbonInterface
    {
        if ($this->tenant === null) {
            return null;
        }

        if ($this->tenant->paid_until !== null) {
            return $this->tenant->paid_until;
        }

        return $this->tenant->status === 'trial' ? $this->tenant->trial_ends_at : null;
    }

    public function graceEndsAt(): ?CarbonInterface
    {
        return $this->deadline()?->copy()->addDays((int) config('plans.grace_days'));
    }

    public function state(): string
    {
        if ($this->tenant === null) {
            return self::ACTIVE;
        }

        if ($this->tenant->status === 'suspended') {
            return self::SUSPENDED;
        }

        $deadline = $this->deadline();
        $now = $this->now ?? Date::now();

        if ($deadline === null || $now->lessThanOrEqualTo($deadline->copy()->endOfDay())) {
            return self::ACTIVE;
        }

        return $now->lessThanOrEqualTo($this->graceEndsAt()?->endOfDay() ?? $now) ? self::GRACE : self::READONLY;
    }

    /** Whole days until the deadline (negative once it has passed); null when there is none. */
    public function daysLeft(): ?int
    {
        $deadline = $this->deadline();

        return $deadline === null ? null : (int) floor((clone ($this->now ?? Date::now()))->startOfDay()->diffInDays($deadline->copy()->startOfDay(), false));
    }

    /** True while the renewal reminder should show. */
    public function shouldWarn(): bool
    {
        $left = $this->daysLeft();

        return $left !== null && $this->state() !== self::SUSPENDED && $left <= (int) config('plans.warn_days');
    }

    public function allowsWrites(): bool
    {
        return in_array($this->state(), [self::ACTIVE, self::GRACE], true);
    }
}
