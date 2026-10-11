<?php

namespace App\Domain\Purchasing\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['name', 'phone', 'email', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** What we still owe this supplier, in minor units. */
    public function outstanding(): int
    {
        return (int) ($this->outstanding_sum ?? $this->purchases()->sum('balance_due'));
    }
}
