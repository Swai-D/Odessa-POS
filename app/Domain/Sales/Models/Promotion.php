<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A price promotion. The arithmetic lives in PromotionEngine; this model holds the rules and turns them into the plain
 * array the engine (and the browser copy of it, public/js/pos-promo.js) works on.
 *
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 */
class Promotion extends Model
{
    use BelongsToTenant;

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    public const BOGO = 'bogo';

    public const BULK = 'bulk';

    public const TYPES = [self::PERCENT, self::FIXED, self::BOGO, self::BULK];

    public const SCOPE_ALL = 'all';

    public const SCOPE_PRODUCTS = 'products';

    public const SCOPE_CATEGORIES = 'categories';

    public const SCOPES = [self::SCOPE_ALL, self::SCOPE_PRODUCTS, self::SCOPE_CATEGORIES];

    protected $fillable = [
        'name', 'type', 'value_kind', 'value', 'min_qty', 'buy_qty', 'get_qty', 'scope', 'scope_ids',
        'starts_on', 'ends_on', 'days', 'time_from', 'time_to', 'code', 'max_uses', 'uses_count', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_qty' => 'float',
            'buy_qty' => 'integer',
            'get_qty' => 'integer',
            'scope_ids' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isCoupon(): bool
    {
        return $this->code !== null && $this->code !== '';
    }

    public function exhausted(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    /** @return array<string, mixed> the shape PromotionEngine (PHP and JS) reads */
    public function toEngine(): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'type' => $this->type,
            'value_kind' => $this->value_kind,
            'value' => $this->value,
            'min_qty' => $this->min_qty,
            'buy_qty' => $this->buy_qty,
            'get_qty' => $this->get_qty,
            'scope' => $this->scope,
            'scope_ids' => array_map('intval', $this->scope_ids ?? []),
            'starts_on' => $this->starts_on?->format('Y-m-d'),
            'ends_on' => $this->ends_on?->format('Y-m-d'),
            'days' => $this->days !== null && $this->days !== '' ? array_map('intval', explode(',', $this->days)) : null,
            'time_from' => $this->time_from,
            'time_to' => $this->time_to,
            'code' => $this->code,
            'is_active' => $this->is_active,
        ];
    }
}
