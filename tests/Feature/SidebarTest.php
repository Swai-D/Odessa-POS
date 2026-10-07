<?php

/** Returns the sidebar's link elements that are marked active. */
function activeSidebarLinks(string $html): array
{
    preg_match('/id="sidebar-menu".*?<\/div>/s', $html, $menu);
    preg_match_all('/<a href="([^"]+)" class="active">/', $menu[0] ?? '', $links);

    return $links[1];
}

it('groups the sidebar into sections like the original template', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['dashboard.view', 'products.view', 'inventory.view', 'sales.view', 'customers.view']);

    $html = $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('<h6 class="submenu-hdr">'.__('app.menu.main').'</h6>')
        ->toContain('<h6 class="submenu-hdr">'.__('app.menu.inventory').'</h6>')
        ->toContain('<h6 class="submenu-hdr">'.__('app.menu.stock').'</h6>')
        ->toContain('<h6 class="submenu-hdr">'.__('app.menu.sales').'</h6>')
        ->toContain('<h6 class="submenu-hdr">'.__('app.menu.peoples').'</h6>')
        // No permission for these sections, so not even their headers show.
        ->not->toContain('<h6 class="submenu-hdr">'.__('app.menu.purchases').'</h6>')
        ->not->toContain('<h6 class="submenu-hdr">'.__('app.menu.settings').'</h6>')
        ->not->toContain('Adrian Herman');
});

it('highlights the section the user is in, also on detail pages', function (): void {
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, array_merge(posPermissions(), ['dashboard.view', 'products.view', 'inventory.view', 'settings.manage']));
    [$product, $warehouse] = posFixture($tenant);
    $saleId = checkout($this, 'shop-a', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->json('id');

    $active = fn (string $url) => activeSidebarLinks(
        $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get($url)->assertOk()->getContent(),
    );

    expect($active('/dashboard'))->toBe([route('dashboard')])
        ->and($active('/products'))->toBe([route('products.index')])
        ->and($active('/stock'))->toBe([route('stock.index')])
        ->and($active('/stock-adjustments'))->toBe([route('stock-adjustments.index')])
        ->and($active('/sales'))->toBe([route('sales.index')])
        ->and($active("/sales/{$saleId}"))->toBe([route('sales.index')])
        ->and($active('/settings'))->toBe([route('settings.index')])
        ->and($active('/settings/integrations'))->toBe([route('settings.integrations')]);
});
