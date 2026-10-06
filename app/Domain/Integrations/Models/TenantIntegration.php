<?php

namespace App\Domain\Integrations\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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
