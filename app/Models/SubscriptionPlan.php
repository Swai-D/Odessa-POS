<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property int|null $monthly_price
 * @property int|null $annual_price
 * @property list<string> $features
 * @property array<string, int|null> $limits
 * @property bool $is_active
 * @property int $sort_order
 */
class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name', 'monthly_price', 'annual_price', 'features', 'limits', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'annual_price' => 'integer',
            'features' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
