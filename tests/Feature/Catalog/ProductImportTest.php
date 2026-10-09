<?php

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;

const IMPORT_HEADER = 'sku,name,barcode,type,category,brand,unit,cost_price,selling_price,tax_rate,tax_inclusive,track_stock,alert_quantity,is_active,opening_stock';

function importShop(string $slug, string $plan = 'medium', array $permissions = ['products.view', 'products.manage']): array
{
    $tenant = createTenant($slug, $plan);
    app(TenantContext::class)->run($tenant, fn () => Warehouse::create(['name' => 'Main Store', 'code' => 'MAIN', 'is_default' => true, 'is_active' => true]));

    return [$tenant, createTenantUser($tenant, $permissions)];
}

function uploadCsv($test, string $slug, $user, string $csv)
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/products/import', [
        'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
    ]);
}

function shopProducts($tenant)
{
    return app(TenantContext::class)->run($tenant, fn () => Product::query()->withSum('stocks', 'quantity')->orderBy('sku')->get());
}

it('creates products with minor-unit prices, opening stock and new categories and units', function (): void {
    [$tenant, $user] = importShop('shop-i1');
    $csv = IMPORT_HEADER."\n".
        "SOAP-1,Soap 250g,600123,standard,Household,Bakhresa,Piece,\"1,500.50\",2500,0,yes,yes,10,yes,50\n".
        "SVC-1,Delivery,,service,,,,0,3000,0,yes,yes,,yes,\n";

    uploadCsv($this, 'shop-i1', $user, $csv)->assertRedirect(route('products.import'))->assertSessionHas('import_result');

    $products = shopProducts($tenant)->keyBy('sku');
    expect($products)->toHaveCount(2)
        ->and($products['SOAP-1']->cost_price)->toBe(150050)
        ->and($products['SOAP-1']->selling_price)->toBe(250000)
        ->and((float) $products['SOAP-1']->stocks_sum_quantity)->toBe(50.0)
        ->and($products['SOAP-1']->category_id)->not->toBeNull()
        ->and($products['SOAP-1']->brand_id)->not->toBeNull()
        ->and($products['SVC-1']->track_stock)->toBeFalse();

    app(TenantContext::class)->run($tenant, function (): void {
        expect(Category::query()->where('name', 'Household')->value('slug'))->toBe('household')
            ->and(Unit::query()->where('name', 'Piece')->exists())->toBeTrue()
            ->and(Brand::query()->where('name', 'Bakhresa')->exists())->toBeTrue();
    });
});

it('updates only the filled-in cells of a known SKU and never touches its stock', function (): void {
    [$tenant, $user] = importShop('shop-i2');
    uploadCsv($this, 'shop-i2', $user, IMPORT_HEADER."\nA-1,Alpha,,standard,,,,100,200,0,yes,yes,5,yes,20\n")->assertSessionHas('import_result');

    $result = uploadCsv($this, 'shop-i2', $user, "sku,name,selling_price,opening_stock\nA-1,Alpha Renamed,350,999\n")->assertSessionHas('import_result');

    $product = shopProducts($tenant)->first();
    expect($product->name)->toBe('Alpha Renamed')
        ->and($product->selling_price)->toBe(35000)
        ->and($product->cost_price)->toBe(10000)
        ->and((float) $product->stocks_sum_quantity)->toBe(20.0)
        ->and(session('import_result')['updated'])->toBe(1)
        ->and(session('import_result')['notes'])->not->toBeEmpty();
});

it('is safe to upload the same file twice', function (): void {
    [$tenant, $user] = importShop('shop-i3');
    $csv = IMPORT_HEADER."\nB-1,Beta,,standard,,,,100,200,0,yes,yes,0,yes,10\n";

    uploadCsv($this, 'shop-i3', $user, $csv);
    uploadCsv($this, 'shop-i3', $user, $csv);

    $products = shopProducts($tenant);
    expect($products)->toHaveCount(1)->and((float) $products->first()->stocks_sum_quantity)->toBe(10.0);
});

it('refuses the whole file and saves nothing when any row has a problem', function (): void {
    [$tenant, $user] = importShop('shop-i4');
    $csv = IMPORT_HEADER."\n".
        "OK-1,Fine,,standard,,,,100,200,0,yes,yes,0,yes,\n".
        "BAD-1,Negative,,standard,,,,-5,200,0,yes,yes,0,yes,\n".
        "OK-1,Repeated sku,,standard,,,,100,200,0,yes,yes,0,yes,\n".
        ",No sku,,standard,,,,100,200,0,yes,yes,0,yes,\n";

    uploadCsv($this, 'shop-i4', $user, $csv)->assertRedirect(route('products.import'))->assertSessionHas('import_problems');

    $problems = implode("\n", session('import_problems'));
    expect(shopProducts($tenant))->toHaveCount(0)
        ->and($problems)->toContain('Line 3')->toContain('Line 4')->toContain('Line 5')
        ->and(session('import_total'))->toBeGreaterThanOrEqual(3);
});

it('reads semicolon-separated files saved by Excel, with a byte order mark', function (): void {
    [$tenant, $user] = importShop('shop-i5');
    $csv = "\xEF\xBB\xBFsku;name;selling_price\nS-1;Semi;1200\n";

    uploadCsv($this, 'shop-i5', $user, $csv)->assertSessionHas('import_result');

    expect(shopProducts($tenant)->first()->selling_price)->toBe(120000);
});

it('rejects unknown columns and a missing sku or name column', function (): void {
    [, $user] = importShop('shop-i6');

    uploadCsv($this, 'shop-i6', $user, "sku,name,colour\nX-1,Thing,red\n")->assertSessionHas('import_problems');
    expect(implode(' ', session('import_problems')))->toContain('colour');

    uploadCsv($this, 'shop-i6', $user, "name,selling_price\nThing,5\n")->assertSessionHas('import_problems');
    expect(implode(' ', session('import_problems')))->toContain('sku');
});

it('refuses a file with more rows than the limit', function (): void {
    [$tenant, $user] = importShop('shop-i7');
    config(['pos.import_max_rows' => 2]);

    uploadCsv($this, 'shop-i7', $user, "sku,name\nA,One\nB,Two\nC,Three\n")->assertSessionHas('import_problems');

    expect(shopProducts($tenant))->toHaveCount(0);
});

it('ignores the brand column on a plan without brands', function (): void {
    [$tenant, $user] = importShop('shop-i8', 'basic');

    uploadCsv($this, 'shop-i8', $user, "sku,name,brand\nBR-1,Branded,Acme\n")->assertSessionHas('import_result');

    expect(shopProducts($tenant)->first()->brand_id)->toBeNull()
        ->and(session('import_result')['notes'])->not->toBeEmpty();
});

it('does not touch or clash with another shop\'s products', function (): void {
    [$tenant, $user] = importShop('shop-i9');
    [$other] = importShop('shop-i9b');
    app(TenantContext::class)->run($other, fn () => Product::create([
        'name' => 'Theirs', 'sku' => 'SAME-1', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true,
    ]));

    uploadCsv($this, 'shop-i9', $user, "sku,name,selling_price\nSAME-1,Mine,900\n")->assertSessionHas('import_result');

    expect(shopProducts($tenant)->first()->name)->toBe('Mine')
        ->and(shopProducts($other)->first()->name)->toBe('Theirs');
});

it('lets a viewer export but not import', function (): void {
    [$tenant, $user] = importShop('shop-ia', 'medium', ['products.view']);
    app(TenantContext::class)->run($tenant, fn () => Product::create([
        'name' => '=HYPERLINK("x")', 'sku' => 'EXP-1', 'type' => 'standard', 'cost_price' => 150050, 'selling_price' => 250000, 'tax_rate' => 0, 'tax_inclusive' => true,
    ]));

    $csv = $this->actingAs($user)->withHeader('X-Tenant', 'shop-ia')->get('/products/export')->assertOk()->streamedContent();
    expect($csv)->toContain('EXP-1')->toContain('1500.50')->toContain("'=HYPERLINK");

    uploadCsv($this, 'shop-ia', $user, "sku,name\nZ-1,Nope\n")->assertForbidden();
});

it('exports only this shop\'s products', function (): void {
    [, $user] = importShop('shop-ib');
    [$other] = importShop('shop-ibb');
    app(TenantContext::class)->run($other, fn () => Product::create([
        'name' => 'Secret thing', 'sku' => 'SEC-1', 'type' => 'standard', 'cost_price' => 1, 'selling_price' => 2, 'tax_rate' => 0, 'tax_inclusive' => true,
    ]));

    $csv = $this->actingAs($user)->withHeader('X-Tenant', 'shop-ib')->get('/products/export')->assertOk()->streamedContent();
    expect($csv)->not->toContain('SEC-1');
});

it('re-imports an exported file without changing anything', function (): void {
    [$tenant, $user] = importShop('shop-ic');
    uploadCsv($this, 'shop-ic', $user, IMPORT_HEADER."\nR-1,Round trip,,standard,Cat,,Piece,100,200,18,no,yes,3,yes,7\n");

    $export = $this->actingAs($user)->withHeader('X-Tenant', 'shop-ic')->get('/products/export')->streamedContent();
    uploadCsv($this, 'shop-ic', $user, $export)->assertSessionHas('import_result');

    $product = shopProducts($tenant)->first();
    expect(shopProducts($tenant))->toHaveCount(1)
        ->and($product->tax_inclusive)->toBeFalse()
        ->and((float) $product->tax_rate)->toBe(18.0)
        ->and((float) $product->stocks_sum_quantity)->toBe(7.0);
});

it('serves the template and the upload page to those who may manage products', function (): void {
    [, $user] = importShop('shop-id');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-id')->get('/products/import/template')->assertOk();
    $this->actingAs($user)->withHeader('X-Tenant', 'shop-id')->get('/products/import')->assertOk()->assertSee('Import products');
});

it('keeps the upload page away from viewers', function (): void {
    [, $user] = importShop('shop-ie', 'medium', ['products.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ie')->get('/products/import')->assertForbidden();
});
