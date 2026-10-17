<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\Promotion;
use App\Support\Plans;
use App\Support\TenantSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Loads a shop's promotions and runs them through PromotionEngine at the shop's local time. */
class PromotionService
{
    public function __construct(private readonly PromotionEngine $engine) {}

    public function timezone(): string
    {
        return (string) ((new TenantSettings)->get('timezone', null) ?: config('pos.timezone'));
    }

    /** @return array{date: string, weekday: int, minutes: int} */
    public function localParts(CarbonInterface $at): array
    {
        $local = $at->copy()->setTimezone($this->timezone());

        return [
            'date' => $local->format('Y-m-d'),
            'weekday' => (int) $local->format('N'),
            'minutes' => (int) $local->format('G') * 60 + (int) $local->format('i'),
        ];
    }

    /** Promotions are part of the Medium plan and up. A shop that drops below it keeps its promotions saved, but they stop applying. */
    public function enabled(): bool
    {
        return Plans::current()->allows('promotions');
    }

    /** Promotions the till applies on its own: switched on, with no code. */
    public function automatic(): Collection
    {
        if (! $this->enabled()) {
            return new Collection;
        }

        return Promotion::query()->where('is_active', true)->whereNull('code')->orderBy('id')->get();
    }

    /**
     * The coupon behind a code, if it can be used right now.
     *
     * @throws ValidationException
     */
    public function findCoupon(string $code, CarbonInterface $at, bool $lock = false): Promotion
    {
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['coupon' => __('promotions.coupon_invalid')]);
        }

        $query = Promotion::query()->where('code', strtoupper(trim($code)))->where('is_active', true);
        $coupon = ($lock ? $query->lockForUpdate() : $query)->first();

        if ($coupon === null || ! $this->engine->running($coupon->toEngine(), $this->localParts($at))) {
            throw ValidationException::withMessages(['coupon' => __('promotions.coupon_invalid')]);
        }

        if ($coupon->exhausted()) {
            throw ValidationException::withMessages(['coupon' => __('promotions.coupon_used_up')]);
        }

        return $coupon;
    }

    /**
     * Discounts per basket line at a moment, plus the coupon if it gave a discount to at least one line.
     *
     * @param  list<array{product_id: int, category_id: int|null, quantity: float|int|string, unit_price: int}>  $lines
     * @return array{promos: list<array{promotion_id: int, name: string, amount: int}|null>, coupon: Promotion|null}
     *
     * @throws ValidationException when a code was entered that cannot be used
     */
    public function resolve(array $lines, ?string $couponCode, CarbonInterface $at, bool $lockCoupon = false): array
    {
        if (! $this->enabled()) {
            // Never refuse a sale because of the plan (an offline sale may arrive after a downgrade): just no discounts.
            return ['promos' => array_fill(0, count($lines), null), 'coupon' => null];
        }

        $code = $couponCode !== null && trim($couponCode) !== '' ? strtoupper(trim($couponCode)) : null;
        $coupon = $code !== null ? $this->findCoupon($code, $at, $lockCoupon) : null;
        $at = $this->localParts($at);

        $promotions = Promotion::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('code')->when($coupon !== null, fn ($q) => $q->orWhere('code', $coupon?->code)))
            ->orderBy('id')->get()
            ->map(fn (Promotion $promotion): array => $promotion->toEngine())->all();

        $promos = $this->engine->evaluate($promotions, $lines, $code, $at);
        $used = $coupon !== null && collect($promos)->contains(fn (?array $promo): bool => $promo !== null && $promo['promotion_id'] === $coupon->getKey());

        return ['promos' => $promos, 'coupon' => $used ? $coupon : null];
    }
}
