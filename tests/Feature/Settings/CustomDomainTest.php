<?php

use App\Domain\Settings\Services\CustomDomainService;
use App\Models\Tenant;
use App\Support\Dns\DnsResolver;
use Illuminate\Support\Facades\Notification;

/** A DNS answer book the tests fill in. */
class FakeDns implements DnsResolver
{
    /** @var array<string, list<string>> */
    public array $txt = [];

    /** @var array<string, list<string>> */
    public array $cname = [];

    /** @var array<string, list<string>> */
    public array $addresses = [];

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }

    public function cname(string $host): array
    {
        return $this->cname[$host] ?? [];
    }

    public function addresses(string $host): array
    {
        return $this->addresses[$host] ?? [];
    }
}

beforeEach(function (): void {
    $this->dns = new FakeDns;
    app()->instance(DnsResolver::class, $this->dns);
    config(['app.domain' => 'odessa-pos.test-host.app', 'pos.custom_domain_target' => 'target.up.railway.app', 'notifications.admin_email' => 'admin@odessa.test']);
});

function domainOwner(string $slug = 'shop-a'): array
{
    $tenant = createTenant($slug);

    return [$tenant, createTenantUser($tenant, ['settings.manage'])];
}

it('normalizes what the owner types', function (): void {
    $service = app(CustomDomainService::class);

    expect($service->normalize('  HTTPS://POS.MyShop.co.tz/login?x=1 '))->toBe('pos.myshop.co.tz')
        ->and($service->normalize('pos.myshop.co.tz.'))->toBe('pos.myshop.co.tz')
        ->and($service->normalize('pos.myshop.co.tz:8080'))->toBe('pos.myshop.co.tz');
});

it('refuses bad, platform and taken names', function (): void {
    [$tenant, $user] = domainOwner();
    $other = createTenant('shop-b');
    $other->update(['domain' => 'taken.example.co.tz']);

    foreach ([['not a domain', 'format'], ['localhost', 'format'], ['shop.odessa-pos.test-host.app', 'platform'], ['x.up.railway.app', 'platform'], ['taken.example.co.tz', 'taken']] as [$input, $key]) {
        $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/settings/domain', ['domain' => $input])
            ->assertSessionHasErrors(['domain' => __('domain.errors.'.$key)]);
    }

    expect($tenant->fresh()->domain_pending)->toBeNull();
});

it('keeps a requested name pending and shows the DNS records', function (): void {
    [$tenant, $user] = domainOwner();

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/settings/domain', ['domain' => 'POS.Mama-Asha.co.tz'])
        ->assertRedirect('/settings/domain');

    $fresh = $tenant->fresh();
    expect($fresh->domain)->toBeNull()->and($fresh->domain_pending)->toBe('pos.mama-asha.co.tz')->and($fresh->domain_token)->not->toBeNull();

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings/domain')->assertOk()
        ->assertSee('_odessa-verify.pos.mama-asha.co.tz')->assertSee('odessa-verify='.$fresh->domain_token)->assertSee('target.up.railway.app');

    // A pending name does not resolve to the shop yet.
    expect(Tenant::query()->where('domain', 'pos.mama-asha.co.tz')->exists())->toBeFalse();
});

it('does not verify without the proof record', function (): void {
    [$tenant, $user] = domainOwner();
    app(CustomDomainService::class)->start($tenant, 'pos.mama-asha.co.tz');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/settings/domain/check')
        ->assertSessionHasErrors(['domain' => __('domain.result.missing')]);

    expect($tenant->fresh()->domain)->toBeNull();
});

it('verifies with the TXT record, activates the name and tells the platform team', function (): void {
    Notification::fake();
    [$tenant, $user] = domainOwner();
    app(CustomDomainService::class)->start($tenant, 'pos.mama-asha.co.tz');
    $this->dns->txt['_odessa-verify.pos.mama-asha.co.tz'] = ['odessa-verify='.$tenant->fresh()->domain_token];
    $this->dns->cname['pos.mama-asha.co.tz'] = ['target.up.railway.app'];

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->post('/settings/domain/check')->assertSessionHas('status');

    $fresh = $tenant->fresh();
    expect($fresh->domain)->toBe('pos.mama-asha.co.tz')->and($fresh->domain_pending)->toBeNull()->and($fresh->domain_token)->toBeNull()
        ->and($fresh->domain_verified_at)->not->toBeNull()->and($fresh->domain_routed_at)->not->toBeNull();
    Notification::assertSentOnDemand(App\Notifications\DomainVerifiedNotification::class);
});

it('verifies but marks the name as not routed when DNS does not point here', function (): void {
    Notification::fake();
    [$tenant] = domainOwner();
    $service = app(CustomDomainService::class);
    $service->start($tenant, 'pos.mama-asha.co.tz');
    $this->dns->txt['_odessa-verify.pos.mama-asha.co.tz'] = ['odessa-verify='.$tenant->fresh()->domain_token];

    expect($service->verify($tenant->fresh()))->toBe('verified')->and($tenant->fresh()->domain_routed_at)->toBeNull();

    // Same addresses as the target also counts as pointing here (for names that cannot use a CNAME).
    $this->dns->addresses['pos.mama-asha.co.tz'] = ['1.2.3.4'];
    $this->dns->addresses['target.up.railway.app'] = ['1.2.3.4'];
    expect($service->refreshRouting($tenant->fresh()))->toBeTrue()->and($tenant->fresh()->domain_routed_at)->not->toBeNull();

    $this->dns->addresses['pos.mama-asha.co.tz'] = ['9.9.9.9'];
    expect($service->refreshRouting($tenant->fresh()))->toBeFalse()->and($tenant->fresh()->domain_routed_at)->toBeNull();
});

it('lets only one shop own a name, even if both asked for it', function (): void {
    Notification::fake();
    [$a] = domainOwner('shop-a');
    $b = createTenant('shop-b');
    $service = app(CustomDomainService::class);
    $service->start($a, 'shared.co.tz');
    $service->start($b, 'shared.co.tz');
    $this->dns->txt['_odessa-verify.shared.co.tz'] = ['odessa-verify='.$a->fresh()->domain_token, 'odessa-verify='.$b->fresh()->domain_token];

    expect($service->verify($a->fresh()))->toBe('verified')->and($service->verify($b->fresh()))->toBe('taken')
        ->and($b->fresh()->domain)->toBeNull();
});

it('serves the shop on its verified domain and not on a pending one', function (): void {
    [$tenant, $user] = domainOwner();
    $tenant->update(['domain' => 'pos.mama-asha.co.tz', 'domain_verified_at' => now()]);

    $this->actingAs($user)->get('http://pos.mama-asha.co.tz/dashboard')->assertOk();
    $this->get('http://pending.mama-asha.co.tz/login')->assertNotFound();
});

it('forwards the shared shop link to a working custom domain only', function (): void {
    $tenant = createTenant('shop-a');
    $host = 'odessa-pos.test-host.app';
    $tenant->update(['domain' => 'pos.mama-asha.co.tz', 'domain_verified_at' => now()]);

    $this->get("http://{$host}/t/shop-a")->assertRedirect('/login');

    $tenant->update(['domain_routed_at' => now()]);
    $this->get("http://{$host}/t/shop-a")->assertRedirect('http://pos.mama-asha.co.tz/');
});

it('lets the owner remove a verified domain but not one the platform set', function (): void {
    [$tenant, $user] = domainOwner();
    $tenant->update(['domain' => 'pos.mama-asha.co.tz']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->delete('/settings/domain')->assertForbidden();

    $tenant->update(['domain_verified_at' => now(), 'domain_routed_at' => now()]);
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->delete('/settings/domain')->assertRedirect('/settings/domain');

    $fresh = $tenant->fresh();
    expect($fresh->domain)->toBeNull()->and($fresh->domain_verified_at)->toBeNull()->and($fresh->domain_routed_at)->toBeNull();
});

it('keeps the domain page behind the settings permission', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/settings/domain')->assertForbidden();
});

it('records which domains point here from the scheduled check', function (): void {
    $tenant = createTenant('shop-a');
    $tenant->update(['domain' => 'pos.mama-asha.co.tz', 'domain_verified_at' => now()]);
    $this->dns->cname['pos.mama-asha.co.tz'] = ['target.up.railway.app'];

    $this->artisan('domains:check')->expectsOutput('1 domain(s) point at this app.')->assertSuccessful();

    expect($tenant->fresh()->domain_routed_at)->not->toBeNull();
});
