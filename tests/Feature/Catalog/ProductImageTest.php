<?php

use App\Domain\Catalog\Models\Product;
use App\Models\StoredFile;
use App\Models\Tenant;
use App\Support\Plans;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pictureShop(string $slug, ?int $storageMb = null): array
{
    Storage::fake('public');
    config(['pos.uploads_disk' => 'public']);

    $tenant = createTenant($slug);

    if ($storageMb !== null) {
        $tenant->update(['settings' => ['plan_overrides' => ['limits' => ['storage_mb' => $storageMb]]]]);
    }

    return [$tenant, createTenantUser($tenant, inventoryAdminPermissions())];
}

function savePicture(object $test, string $slug, $user, ?UploadedFile $image, array $extra = [], ?Product $product = null)
{
    $payload = productPayload($extra + ['image' => $image]);
    $request = $test->actingAs($user)->withHeader('X-Tenant', $slug);

    return $product === null
        ? $request->post('/products', $payload)
        : $request->put('/products/'.$product->id, $payload);
}

function fillStorage(Tenant $tenant, int $bytes): StoredFile
{
    return inTenant($tenant, fn () => StoredFile::query()->create(['disk' => 'public', 'path' => 'x/filler.webp', 'purpose' => 'product', 'size' => $bytes]));
}

it('saves a product picture shrunk to 800 px and counts it against storage', function (): void {
    [$tenant, $user] = pictureShop('pic-a');

    savePicture($this, 'pic-a', $user, UploadedFile::fake()->image('big.jpg', 2000, 1500))
        ->assertSessionHasNoErrors()->assertRedirect(route('products.index'));

    $product = inTenant($tenant, fn () => Product::query()->with('imageFile')->firstOrFail());
    $file = $product->imageFile;

    expect($file)->not->toBeNull()
        ->and($file->path)->toStartWith('tenants/'.$tenant->getKey().'/products/')
        ->and($file->purpose)->toBe('product')
        ->and($file->tenant_id)->toBe($tenant->getKey());

    Storage::disk('public')->assertExists($file->path);

    $bytes = Storage::disk('public')->get($file->path);
    [$width, $height] = getimagesizefromstring($bytes);

    expect($file->size)->toBe(strlen($bytes))->and(max($width, $height))->toBe(800)->and($width)->toBeGreaterThan($height);
});

it('replaces the picture and deletes the old file', function (): void {
    [$tenant, $user] = pictureShop('pic-b');

    savePicture($this, 'pic-b', $user, UploadedFile::fake()->image('one.jpg', 400, 400));
    $product = inTenant($tenant, fn () => Product::query()->with('imageFile')->firstOrFail());
    $first = $product->imageFile->path;

    savePicture($this, 'pic-b', $user, UploadedFile::fake()->image('two.png', 300, 300), [], $product)->assertSessionHasNoErrors();

    $now = inTenant($tenant, fn () => Product::query()->with('imageFile')->firstOrFail()->imageFile);

    expect($now->path)->not->toBe($first)->and(inTenant($tenant, fn () => StoredFile::query()->count()))->toBe(1);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($now->path);
});

it('keeps the picture when the product is edited without a new one, and removes it on request', function (): void {
    [$tenant, $user] = pictureShop('pic-c');

    savePicture($this, 'pic-c', $user, UploadedFile::fake()->image('one.jpg', 400, 400));
    $product = inTenant($tenant, fn () => Product::query()->firstOrFail());

    savePicture($this, 'pic-c', $user, null, ['name' => 'Rice 2kg'], $product)->assertSessionHasNoErrors();
    expect(inTenant($tenant, fn () => Product::query()->firstOrFail()->image_file_id))->not->toBeNull();

    $path = inTenant($tenant, fn () => StoredFile::query()->firstOrFail()->path);

    savePicture($this, 'pic-c', $user, null, ['remove_image' => 1], $product)->assertSessionHasNoErrors();

    expect(inTenant($tenant, fn () => Product::query()->firstOrFail()->image_file_id))->toBeNull()
        ->and(inTenant($tenant, fn () => StoredFile::query()->count()))->toBe(0);
    Storage::disk('public')->assertMissing($path);
});

it('rejects files that are not pictures and saves nothing', function (): void {
    [$tenant, $user] = pictureShop('pic-d');

    savePicture($this, 'pic-d', $user, UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf'))->assertSessionHasErrors('image');
    savePicture($this, 'pic-d', $user, UploadedFile::fake()->createWithContent('fake.jpg', 'this is not an image'))->assertSessionHasErrors('image');

    expect(inTenant($tenant, fn () => Product::query()->count()))->toBe(0)
        ->and(inTenant($tenant, fn () => StoredFile::query()->count()))->toBe(0);
});

it('refuses a picture that does not fit the plan\'s storage, without saving the product', function (): void {
    [$tenant, $user] = pictureShop('pic-e', 1);
    fillStorage($tenant, 1024 * 1024);

    savePicture($this, 'pic-e', $user, UploadedFile::fake()->image('p.jpg', 600, 600))->assertSessionHasErrors('image');

    expect(inTenant($tenant, fn () => Product::query()->count()))->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('lets a replacement use the space the old picture gives back', function (): void {
    [$tenant, $user] = pictureShop('pic-f', 1);

    savePicture($this, 'pic-f', $user, UploadedFile::fake()->image('one.jpg', 400, 400));
    $product = inTenant($tenant, fn () => Product::query()->firstOrFail());
    $old = inTenant($tenant, fn () => StoredFile::query()->firstOrFail());

    // The shop is full apart from what this picture uses; swapping it must still work.
    $old->update(['size' => 1024 * 1024 - 1]);

    savePicture($this, 'pic-f', $user, UploadedFile::fake()->image('two.jpg', 400, 400), [], $product)->assertSessionHasNoErrors();

    expect(inTenant($tenant, fn () => StoredFile::query()->count()))->toBe(1);
});

it('counts only the shop\'s own files', function (): void {
    [$tenant, $user] = pictureShop('pic-g', 1);
    $other = createTenant('pic-g2');
    fillStorage($other, 50 * 1024 * 1024);

    savePicture($this, 'pic-g', $user, UploadedFile::fake()->image('p.jpg', 400, 400))->assertSessionHasNoErrors();

    expect(inTenant($tenant, fn () => StoredFile::query()->count()))->toBe(1);
});

it('has default storage limits per plan and none for a platform admin', function (): void {
    $basic = createTenant('pic-h1', 'basic');
    $medium = createTenant('pic-h2', 'medium');
    $enterprise = createTenant('pic-h3', 'enterprise');

    expect(inTenant($basic, fn () => Plans::current()->limit('storage_mb')))->toBe(100)
        ->and(inTenant($medium, fn () => Plans::current()->limit('storage_mb')))->toBe(500)
        ->and(inTenant($enterprise, fn () => Plans::current()->limit('storage_mb')))->toBe(2000)
        ->and(Plans::current()->limit('storage_mb'))->toBeNull();
});

it('shows the picture in the product list and the usage on the billing page', function (): void {
    [$tenant, $user] = pictureShop('pic-i');
    $owner = createTenantUser($tenant, ['settings.manage', 'products.view']);

    savePicture($this, 'pic-i', $user, UploadedFile::fake()->image('p.jpg', 400, 400));
    $url = inTenant($tenant, fn () => Product::query()->firstOrFail()->imageUrl());

    $this->actingAs($owner)->withHeader('X-Tenant', 'pic-i')->get('/products')->assertOk()->assertSee($url, false);
    $this->actingAs($owner)->withHeader('X-Tenant', 'pic-i')->get('/billing')->assertOk()->assertSee('Storage used')->assertSee('2000 MB');
});
