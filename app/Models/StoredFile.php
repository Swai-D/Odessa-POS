<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** One file kept on the uploads disk for a shop. The sum of `size` is what counts against the plan's storage limit. */
class StoredFile extends Model
{
    use BelongsToTenant;

    protected $fillable = ['disk', 'path', 'purpose', 'mime', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
