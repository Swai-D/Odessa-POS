<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array{items?: list<array<string, mixed>>, discount?: array<string, mixed>|null} $payload
 */
class HeldOrder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'customer_id', 'reference', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
