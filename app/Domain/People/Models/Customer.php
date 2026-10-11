<?php

namespace App\Domain\People\Models;

use App\Domain\Sales\Models\Sale;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['name', 'phone', 'email', 'address', 'credit_limit', 'is_active'];

    protected function casts(): array
    {
        return ['credit_limit' => 'integer', 'is_active' => 'boolean'];
    }

    /** Credit limit in major units for edit forms (empty when unlimited). */
    protected function creditLimitInput(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->credit_limit === null ? null : Money::toMajor($this->credit_limit));
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Unpaid balance across all sales, in minor units. */
    public function outstanding(): int
    {
        return (int) ($this->outstanding_sum ?? $this->sales()->sum('balance_due'));
    }
}
