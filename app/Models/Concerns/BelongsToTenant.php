<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenant = app(TenantContext::class)->get();

            if ($tenant) {
                $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenant->getKey());
            } else {
                $builder->whereNull($builder->getModel()->qualifyColumn('tenant_id'));
            }
        });

        static::creating(function (Model $model): void {
            if ($tenant = app(TenantContext::class)->get()) {
                $model->setAttribute('tenant_id', $tenant->getKey());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
