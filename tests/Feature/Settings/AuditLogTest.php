<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\StockService;
use App\Domain\People\Models\Customer;
use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Domain\Settings\Models\AuditLog;
use App\Domain\Settings\Services\AuditRecorder;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** @return array{0: Tenant, 1: User} */
function auditShop(string $slug, string $plan = 'enterprise', array $permissions = ['audit.view', 'settings.manage']): array
{
    $tenant = createTenant($slug, $plan);
    $user = createTenantUser($tenant, $permissions);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    foreach (['Owner', 'Cashier'] as $name) {
        Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->getKey()]);
    }

    return [$tenant, $user];
}

function auditEntries($tenant, array $where = []): array
{
    return app(TenantContext::class)->run($tenant, fn () => AuditLog::query()->where($where)->orderBy('id')->get()->all());
}

function auditProduct($tenant, string $name = 'Sugar', int $cost = 150_000): Product
{
    return app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => $name, 'sku' => strtoupper($name), 'type' => 'standard',
        'cost_price' => $cost, 'selling_price' => 200_000, 'tax_rate' => 0, 'tax_inclusive' => true,
    ]));
}

it('records who created a record and what it held', function () {
    [$tenant, $user] = auditShop('audit-a');
    $this->actingAs($user);

    auditProduct($tenant, 'Sugar');

    [$entry] = auditEntries($tenant, ['subject_type' => 'Product']);
    expect($entry->event)->toBe('created')
        ->and($entry->subject_label)->toBe('Sugar')
        ->and($entry->user_id)->toBe($user->getKey())
        ->and($entry->user_name)->toBe($user->name)
        ->and($entry->changes['selling_price']['new'])->toBe(200_000)
        ->and($entry->changes['selling_price']['old'])->toBeNull();
});

it('records the old and new value of a change', function () {
    [$tenant, $user] = auditShop('audit-b');
    $product = auditProduct($tenant);
    $this->actingAs($user);

    app(TenantContext::class)->run($tenant, fn () => $product->update(['cost_price' => 180_000]));

    $entries = auditEntries($tenant, ['event' => 'updated']);
    expect($entries)->toHaveCount(1)
        ->and($entries[0]->changes)->toBe(['cost_price' => ['old' => 150_000, 'new' => 180_000]]);
});

it('ignores updates that only touch bookkeeping columns', function () {
    [$tenant] = auditShop('audit-c');
    $product = auditProduct($tenant);

    app(TenantContext::class)->run($tenant, fn () => $product->touch());

    expect(auditEntries($tenant, ['event' => 'updated']))->toBeEmpty();
});

it('records a deletion', function () {
    [$tenant, $user] = auditShop('audit-d');
    $customer = app(TenantContext::class)->run($tenant, fn () => Customer::create(['name' => 'Asha']));
    $this->actingAs($user);

    app(TenantContext::class)->run($tenant, fn () => $customer->delete());

    $entries = auditEntries($tenant, ['event' => 'deleted']);
    expect($entries)->toHaveCount(1)->and($entries[0]->subject_label)->toBe('Asha');
});

it('never writes a password into the log', function () {
    [$tenant] = auditShop('audit-e');
    $person = app(TenantContext::class)->run($tenant, fn () => User::query()->first());

    app(TenantContext::class)->run($tenant, fn () => $person->forceFill(['password' => 'new-secret-pass', 'name' => 'Renamed'])->save());

    $json = json_encode(DB::table('audit_logs')->get());
    expect($json)->not->toContain('new-secret-pass')->and($json)->not->toContain('password');
});

it('records manual stock adjustments but not stock moved by sales', function () {
    [$tenant, $user] = auditShop('audit-f');
    $product = auditProduct($tenant);
    $this->actingAs($user);

    app(TenantContext::class)->run($tenant, function () use ($product, $user): void {
        $warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN', 'is_default' => true]);
        $stock = app(StockService::class);
        $stock->move($product, $warehouse, 10, StockMovement::PURCHASE, null, $user);
        $stock->move($product, $warehouse, -2, StockMovement::ADJUSTMENT_OUT, 'Damaged', $user);
    });

    $entries = auditEntries($tenant, ['subject_type' => 'StockMovement']);
    expect($entries)->toHaveCount(1)
        ->and($entries[0]->subject_label)->toBe('Sugar')
        ->and($entries[0]->changes['reason']['new'])->toBe('Damaged');
});

it('records a change of role when staff are added', function () {
    [$tenant, $user] = auditShop('audit-g');

    $this->actingAs($user)->withHeader('X-Tenant', 'audit-g')->post('/users', [
        'name' => 'Neema', 'email' => 'neema@example.com', 'role' => 'Cashier', 'password' => 'secret-pass-1',
    ])->assertSessionHasNoErrors();

    $entries = auditEntries($tenant, ['event' => 'role_changed']);
    expect($entries)->toHaveCount(1)
        ->and($entries[0]->subject_label)->toBe('Neema')
        ->and($entries[0]->changes['role'])->toBe(['old' => null, 'new' => 'Cashier']);
});

it('records custom role changes', function () {
    [$tenant, $user] = auditShop('audit-h');

    $this->actingAs($user)->withHeader('X-Tenant', 'audit-h')
        ->post('/roles', ['name' => 'Supervisor', 'permissions' => ['sales.view']])->assertSessionHasNoErrors();

    $entries = auditEntries($tenant, ['subject_type' => 'Role']);
    expect($entries)->toHaveCount(1)->and($entries[0]->event)->toBe('created')
        ->and($entries[0]->changes['permissions']['new'])->toBe('sales.view');
});

it('records nothing outside a shop', function () {
    app(AuditRecorder::class)->note('updated', 'Product', 1, 'Anything');

    expect(DB::table('audit_logs')->count())->toBe(0);
});

it('keeps the name of a person who is later removed', function () {
    [$tenant, $user] = auditShop('audit-i');
    $this->actingAs($user);
    auditProduct($tenant);
    $name = $user->name;

    DB::table('users')->where('id', $user->getKey())->delete();

    $entry = DB::table('audit_logs')->where('subject_type', 'Product')->first();
    expect($entry->user_id)->toBeNull()->and($entry->user_name)->toBe($name);
});

it('shows the log to someone with the permission on Enterprise, with money formatted', function () {
    [$tenant, $user] = auditShop('audit-j');
    $this->actingAs($user);
    auditProduct($tenant, 'Sugar', 150_000);

    $page = $this->get('/audit-log', ['X-Tenant' => 'audit-j'])->assertOk();

    expect($page->getContent())->toContain('Sugar')->toContain('TZS 1,500.00')->toContain($user->name);
});

it('keeps the log of one shop away from another', function () {
    [$a] = auditShop('audit-k');
    [, $userB] = auditShop('audit-l');
    auditProduct($a, 'Secret Item');

    $page = $this->actingAs($userB)->get('/audit-log', ['X-Tenant' => 'audit-l'])->assertOk();

    expect($page->getContent())->not->toContain('Secret Item');
});

it('filters by kind of record and by action', function () {
    [$tenant, $user] = auditShop('audit-m');
    $this->actingAs($user);
    auditProduct($tenant, 'Sugar');
    app(TenantContext::class)->run($tenant, fn () => Customer::create(['name' => 'Neema Client']));

    $products = $this->get('/audit-log?type=Product', ['X-Tenant' => 'audit-m'])->assertOk()->getContent();
    expect($products)->toContain('Sugar')->not->toContain('Neema Client');

    $deleted = $this->get('/audit-log?event=deleted', ['X-Tenant' => 'audit-m'])->assertOk()->getContent();
    expect($deleted)->not->toContain('Sugar');
});

it('is only for Enterprise shops', function (string $plan) {
    [, $user] = auditShop('audit-n', $plan);

    $this->actingAs($user)->get('/audit-log', ['X-Tenant' => 'audit-n'])->assertForbidden();
})->with(['basic', 'medium']);

it('needs the audit permission', function () {
    [, $user] = auditShop('audit-o', 'enterprise', ['settings.manage']);

    $this->actingAs($user)->get('/audit-log', ['X-Tenant' => 'audit-o'])->assertForbidden();
});

it('records on every plan so an upgrade shows the history', function () {
    [$tenant] = auditShop('audit-p', 'basic');
    auditProduct($tenant);

    expect(auditEntries($tenant))->not->toBeEmpty();
});

it('gives the permission to the Owner but not the Manager by default', function () {
    $roles = ProvisionTenantAction::rolePermissions();

    expect($roles['Owner'])->toContain('audit.view')->and($roles['Manager'])->not->toContain('audit.view');
});

it('shows the entry locked in the Medium sidebar', function () {
    [, $user] = auditShop('audit-q', 'medium');

    $html = $this->actingAs($user)->get('/dashboard', ['X-Tenant' => 'audit-q'])->assertOk()->getContent();

    expect($html)->toContain('<a href="'.route('audit.index').'" class="text-muted" data-locked="1"');
});
