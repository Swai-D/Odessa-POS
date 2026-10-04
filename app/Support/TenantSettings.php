<?php

namespace App\Support;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;

class TenantSettings
{
    public function __construct(private readonly ?Tenant $tenant = null) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $tenant = $this->tenant ?? app(TenantContext::class)->get();

        return data_get($tenant ? $tenant->settings : [], $key, $default);
    }

    public function feature(string $feature): bool
    {
        return (bool) $this->get('features.'.$feature, config('pos.features.'.$feature, false));
    }
}
