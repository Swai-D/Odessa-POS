<?php

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $settings
 * @property array<string, mixed>|null $secrets
 */
class TenantIntegration extends Model
{
    use BelongsToTenant;

    protected $fillable = ['channel', 'driver', 'settings', 'secrets'];

    protected $hidden = ['secrets'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'secrets' => 'encrypted:array'];
    }
}
