<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_template_assets_and_fortify_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee('name="email"', false)
            ->assertSee(asset('assets/css/style.css'), false);
    }

    public function test_dashboard_renders_the_original_demo_content_with_configured_menu(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Admin')
            ->assertSee(__('app.menu.inventory'))
            ->assertSee(asset('assets/js/script.js'), false);
    }

    public function test_pos_renders_the_fullscreen_template_body(): void
    {
        $tenant = createTenant('shop-a');
        $user = createTenantUser($tenant, ['pos.access']);

        $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/pos')
            ->assertOk()
            ->assertSee('pos-wrapper', false)
            ->assertSee(asset('assets/js/calculator.js'), false);
    }

    public function test_pos_explains_that_a_super_admin_has_no_shop(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($user)->get('/pos')->assertForbidden();
    }

    public function test_unknown_routes_use_the_template_404_view(): void
    {
        $this->get('/not-a-real-page')
            ->assertNotFound()
            ->assertSee('Page not found');
    }
}
