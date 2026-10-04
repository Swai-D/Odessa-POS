<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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
