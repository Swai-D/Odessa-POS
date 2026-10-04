<?php

namespace App\Domain\Catalog\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'short_name', 'allow_decimal', 'is_active'];

    protected function casts(): array
    {
        return ['allow_decimal' => 'boolean', 'is_active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
