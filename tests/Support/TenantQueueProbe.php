<?php

namespace Tests\Support;

use App\Http\Middleware\RestoreTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TenantQueueProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static ?string $tenantIdSeen = null;

    public function handle(): void
    {
        self::$tenantIdSeen = app(TenantContext::class)->get()?->getKey();
    }

    public function middleware(): array
    {
        return [new RestoreTenantContext];
    }
}
