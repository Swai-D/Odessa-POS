<?php

use App\Domain\Sales\Models\Promotion;
use App\Domain\Sales\Services\PromotionService;
use App\Support\Plans;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function gatedShop(string $plan): array
{
    $tenant = createTenant('gate-'.$plan, $plan);
    $user = createTenantUser($tenant, ['sales.view', 'sales.manage', 'pos.access', 'settings.manage', 'dashboard.view']);

    return [$tenant, $user, 'gate-'.$plan];
}

it('keeps promotions off the basic plan', function () {
    [, $user, $slug] = gatedShop('basic');

    $this->actingAs($user)->withHeader('X-Tenant', $slug)->get('/promotions')->assertForbidden()->assertSee('Medium');
});

it('opens promotions from the medium plan up', function (string $plan) {
    [, $user, $slug] = gatedShop($plan);

    $this->actingAs($user)->withHeader('X-Tenant', $slug)->get('/promotions')->assertOk();
})->with(['medium', 'enterprise']);

it('reserves the custom domain for enterprise', function () {
    foreach (['basic', 'medium'] as $plan) {
        [, $user, $slug] = gatedShop($plan);
        $this->actingAs($user)->withHeader('X-Tenant', $slug)->get('/settings/domain')->assertForbidden();
    }

    [, $user, $slug] = gatedShop('enterprise');
    $this->actingAs($user)->withHeader('X-Tenant', $slug)->get('/settings/domain')->assertOk();
});

it('names the smallest plan for the new features', function () {
    expect(Plans::cheapestPlanFor('promotions'))->toBe('medium')
        ->and(Plans::cheapestPlanFor('custom_domain'))->toBe('enterprise');
});

it('applies no promotion and no coupon when the plan lacks them, yet still resolves the sale', function () {
    [$tenant] = gatedShop('basic');
    app(TenantContext::class)->run($tenant, function () {
        Promotion::query()->create(['name' => 'Promo', 'type' => 'percent', 'value' => 1000, 'scope' => 'all', 'is_active' => true]);
        $service = app(PromotionService::class);

        expect($service->enabled())->toBeFalse()
            ->and($service->automatic())->toBeEmpty();

        $result = $service->resolve([['product_id' => 'x', 'quantity' => 1]], 'ANYCODE', CarbonImmutable::now());

        expect($result['promos'])->toBe([null])->and($result['coupon'])->toBeNull();
    });
});

it('lets a medium shop use promotions', function () {
    [$tenant] = gatedShop('medium');
    app(TenantContext::class)->run($tenant, fn () => expect(app(PromotionService::class)->enabled())->toBeTrue());
});

it('adds promotions to existing medium plans through the migration', function () {
    $migration = require database_path('migrations/2026_10_29_000001_add_promotions_and_custom_domain_plan_features.php');
    $migration->up();

    $features = fn (string $code) => (array) json_decode((string) DB::table('subscription_plans')->where('code', $code)->value('features'), true);

    expect($features('medium'))->toContain('promotions')
        ->and($features('basic'))->not->toContain('promotions')
        ->and($features('enterprise'))->toContain('*');
});
