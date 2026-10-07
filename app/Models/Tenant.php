<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $paid_until
 */
class Tenant extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['name', 'slug', 'domain', 'status', 'plan', 'trial_ends_at', 'paid_until', 'settings'];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime', 'paid_until' => 'datetime', 'settings' => 'array'];
    }
}
