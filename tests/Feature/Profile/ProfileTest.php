<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the real user in the navbar with only the links they may use', function (): void {
    $tenant = createTenant('shop-a');
    $owner = createTenantUser($tenant, ['dashboard.view', 'settings.manage', 'reports.view']);
    $owner->update(['name' => 'Asha Mushi']);
    $cashier = createTenantUser($tenant, ['dashboard.view']);

    $page = $this->actingAs($owner)->withHeader('X-Tenant', 'shop-a')->get('/dashboard')->assertOk();
    $page->assertSee('Asha Mushi')
        ->assertDontSee('John Smilga')
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('settings.index'), false)
        ->assertSee(route('reports.index'), false)
        ->assertSee(route('logout'), false);

    $this->actingAs($cashier)->withHeader('X-Tenant', 'shop-a')->get('/dashboard')->assertOk()
        ->assertSee(route('profile.edit'), false)
        ->assertDontSee(route('settings.index'), false)
        ->assertDontSee(route('reports.index'), false);
});

it('updates the profile, including language, and rejects a taken email', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['dashboard.view']);
    $other = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/profile', [
        'name' => 'New Name', 'email' => 'new@example.test', 'locale' => 'sw',
    ])->assertSessionHasNoErrors()->assertRedirect('/profile');

    expect($user->fresh())->name->toBe('New Name')->email->toBe('new@example.test')->locale->toBe('sw');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/profile', [
        'name' => 'New Name', 'email' => $other->email, 'locale' => 'en',
    ])->assertSessionHasErrors('email');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/profile', [
        'name' => 'X', 'email' => 'new@example.test', 'locale' => 'fr',
    ])->assertSessionHasErrors('locale');
});

it('changes the password only with the correct current one', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['dashboard.view']);
    $user->update(['password' => 'old-password-1']);

    $change = fn (array $data) => $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->put('/profile/password', $data);

    $change(['current_password' => 'wrong', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
        ->assertSessionHasErrors('current_password');
    $change(['current_password' => 'old-password-1', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $change(['current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors('password');
    $change(['current_password' => 'old-password-1', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
        ->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('lets a super admin with no shop open the profile page', function (): void {
    $admin = User::factory()->create(['tenant_id' => null]);
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($admin)->get('/profile')->assertOk()->assertSee('Super Admin');
});

it('requires sign-in for the profile page', function (): void {
    $this->get('/profile')->assertRedirect();
});
