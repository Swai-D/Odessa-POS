<?php

namespace App\Domain\Sales\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['key', 'last_value'];
}
