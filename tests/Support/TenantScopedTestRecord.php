<?php

namespace Tests\Support;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TenantScopedTestRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_scoped_test_records';

    protected $guarded = [];
}
