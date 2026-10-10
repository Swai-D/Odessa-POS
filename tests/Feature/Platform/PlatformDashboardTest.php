<?php

use App\Models\Tenant;
use App\Models\TenantPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

it('shows platform-wide shop, renewal and payment information to super admins', function (): void {
    Carbon::setTestNow('2026-10-10 12:00:00');

    $active = createTenant('active-shop');
    $active->update(['name' => 'Active Shop', 'paid_until' => Carbon::today()->addDays(4)]);
    $trial = createTenant('trial-shop');
    $trial->update(['name' => 'Trial Shop', 'status' => 'trial', 'trial_ends_at' => Carbon::today()->addDays(2)]);
    $suspended = createTenant('suspended-shop');
    $suspended->update(['name' => 'Suspended Shop', 'status' => 'suspended', 'paid_until' => Carbon::today()->addDay()]);
    $overdue = createTenant('overdue-shop');
    $overdue->update(['name' => 'Overdue Shop', 'paid_until' => Carbon::today()->subDays(3)]);

    $recordPayment = static function (Tenant $tenant, string $key, int $amount, string $currency, Carbon $paidOn): void {
        TenantPayment::query()->create([
            'tenant_id' => $tenant->getKey(),
            'idempotency_key' => $key,
            'plan' => 'medium',
            'amount' => $amount,
            'currency' => $currency,
            'method' => 'cash',
            'months' => 1,
            'paid_on' => $paidOn->toDateString(),
            'period_start' => $paidOn->toDateString(),
            'period_end' => $paidOn->copy()->addMonth()->toDateString(),
        ]);
    };
    $recordPayment($active, (string) Str::uuid(), 1_250_000, 'TZS', Carbon::today());
    $recordPayment($trial, (string) Str::uuid(), 250_000, 'USD', Carbon::today());
    $recordPayment($overdue, (string) Str::uuid(), 200_000, 'TZS', Carbon::today()->subMonth());

    $response = $this->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->get('/platform')
        ->assertOk()
        ->assertSee(__('platform.dashboard.title'))
        ->assertSee(route('platform.payments.index'))
        ->assertSee('Active Shop')
        ->assertSee('Trial Shop')
        ->assertSee('Overdue Shop')
        ->assertSee('TZS 12,500.00')
        ->assertSee('USD 2,500.00');

    $response->assertViewHas('overview', static function (array $overview) use ($active, $trial, $suspended, $overdue): bool {
        $renewalIds = $overview['renewals']->modelKeys();
        $revenue = array_column($overview['revenueByCurrency'], 'amount', 'currency');

        expect($overview['shops'])->toBe(4)
            ->and($overview['active'])->toBe(2)
            ->and($overview['trial'])->toBe(1)
            ->and($overview['suspended'])->toBe(1)
            ->and($revenue)->toEqualCanonicalizing(['USD' => 250_000, 'TZS' => 1_250_000])
            ->and(in_array($active->getKey(), $renewalIds, true))->toBeTrue()
            ->and(in_array($trial->getKey(), $renewalIds, true))->toBeTrue()
            ->and(in_array($overdue->getKey(), $renewalIds, true))->toBeTrue()
            ->and(in_array($suspended->getKey(), $renewalIds, true))->toBeFalse();

        return true;
    });

    Carbon::setTestNow();
});

it('keeps the platform overview away from guests and shop users', function (): void {
    $tenant = createTenant('platform-denied');
    $shopUser = createTenantUser($tenant, ['dashboard.view']);

    $this->get('/platform')->assertRedirect('/login');
    $this->actingAs($shopUser)->withHeader('X-Tenant', 'platform-denied')->get('/platform')->assertForbidden();
});
