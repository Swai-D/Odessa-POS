<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TenantQueueProbe;
use Tests\Support\TenantScopedTestRecord;

beforeEach(function (): void {
    Schema::dropIfExists('tenant_scoped_test_records');
    Schema::create('tenant_scoped_test_records', function (Blueprint $table): void {
        $table->id();
        $table->foreignUlid('tenant_id')->constrained('tenants');
        $table->string('name');
        $table->timestamps();
    });
});

function makeTenant(string $slug): Tenant
{
    return Tenant::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'status' => 'active',
    ]);
}

it('prevents one tenant from reading another tenants rows', function (): void {
    $first = makeTenant('first');
    $second = makeTenant('second');
    $context = app(TenantContext::class);

    $context->run($first, fn () => TenantScopedTestRecord::create(['name' => 'first row']));
    $context->run($second, fn () => TenantScopedTestRecord::create(['name' => 'second row']));

    expect($context->run($first, fn () => TenantScopedTestRecord::query()->pluck('name')->all()))
        ->toBe(['first row']);
});

it('automatically assigns the active tenant when creating a tenant-owned row', function (): void {
    $tenant = makeTenant('autofill');
    $otherTenant = makeTenant('other');

    $record = app(TenantContext::class)->run(
        $tenant,
        fn () => TenantScopedTestRecord::create([
            'name' => 'created',
            'tenant_id' => $otherTenant->getKey(),
        ]),
    );

    expect($record->tenant_id)->toBe($tenant->getKey());
});

it('resolves a tenant from its subdomain', function (): void {
    config(['app.domain' => 'pos.test']);
    makeTenant('demo');

    Route::get('/_tenant-context', fn () => app(TenantContext::class)->get()?->slug)
        ->middleware(ResolveTenant::class);

    $this->get('http://demo.pos.test/_tenant-context')->assertOk()->assertSeeText('demo');
});

it('stores the tenant identifier in queued job payloads', function (): void {
    $tenant = makeTenant('queued');

    app(TenantContext::class)->run($tenant, function (): void {
        TenantQueueProbe::dispatch();
    });

    $payload = json_decode((string) DB::table('jobs')->latest('id')->value('payload'), true);

    expect($payload['tenant_id'] ?? null)->toBe($tenant->getKey());

    Artisan::call('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--sleep' => 0,
        '--tries' => 1,
    ]);

    expect(TenantQueueProbe::$tenantIdSeen)->toBe($tenant->getKey());
    expect(app(TenantContext::class)->get())->toBeNull();
});
