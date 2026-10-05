<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\People\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\UnitPolicy;
use App\Policies\WarehousePolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-settings', fn (User $user): bool => $user->is_super_admin || $user->can('settings.manage'));
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);

        Queue::createPayloadUsing(function (): array {
            $tenant = app(TenantContext::class)->get();

            return $tenant ? ['tenant_id' => $tenant->getKey()] : [];
        });

        Queue::before(function (JobProcessing $event): void {
            $context = app(TenantContext::class);
            $context->forget();
            $tenantId = data_get($event->job->payload(), 'tenant_id');

            if ($tenantId !== null && ($tenant = Tenant::find($tenantId))) {
                $context->set($tenant);
            }
        });

        $clearTenantContext = fn () => app(TenantContext::class)->forget();
        Queue::after(fn (JobProcessed $event) => $clearTenantContext());
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => $clearTenantContext());
    }
}
