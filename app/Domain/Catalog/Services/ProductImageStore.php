<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Settings\Services\TenantStorage;
use App\Models\StoredFile;
use App\Support\Tenancy\TenantContext;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps one picture per product. Every upload is re-encoded (so only a real image gets through and EXIF data and
 * oversized originals are dropped), scaled to fit 800 px and saved as WebP (JPEG where WebP is unavailable).
 * The result is counted against the shop's storage allowance.
 */
class ProductImageStore
{
    private const MAX_SIDE = 800;

    public function __construct(private readonly TenantStorage $storage) {}

    /**
     * Process the upload and check it fits, without touching the disk. Throws a validation error on the `image` field.
     *
     * @return array{bytes: string, extension: string, mime: string}
     */
    public function prepare(UploadedFile $upload, ?Product $replacing = null): array
    {
        $image = $this->decode($upload);
        $prepared = $this->encode($image);

        $releasing = $replacing === null ? 0 : (int) $replacing->imageFile()->value('size');

        if (! $this->storage->fits(strlen($prepared['bytes']), $releasing)) {
            throw ValidationException::withMessages(['image' => __('catalog.image.storage_full')]);
        }

        return $prepared;
    }

    /** @param  array{bytes: string, extension: string, mime: string}  $prepared */
    public function attach(Product $product, array $prepared): void
    {
        $tenantId = (string) app(TenantContext::class)->get()?->getKey();
        $disk = $this->storage->diskName();
        $path = 'tenants/'.$tenantId.'/products/'.Str::lower((string) Str::ulid()).'.'.$prepared['extension'];

        Storage::disk($disk)->put($path, $prepared['bytes'], 'public');

        $file = StoredFile::query()->create([
            'disk' => $disk,
            'path' => $path,
            'purpose' => 'product',
            'mime' => $prepared['mime'],
            'size' => strlen($prepared['bytes']),
        ]);

        $old = $product->imageFile;
        $product->forceFill(['image_file_id' => $file->getKey()])->save();

        if ($old !== null) {
            $this->storage->delete($old);
        }
    }

    public function remove(Product $product): void
    {
        $old = $product->imageFile;

        if ($old === null) {
            return;
        }

        $product->forceFill(['image_file_id' => null])->save();
        $this->storage->delete($old);
    }

    private function decode(UploadedFile $upload): GdImage
    {
        $contents = (string) @file_get_contents((string) $upload->getRealPath());
        $image = $contents === '' ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['image' => __('catalog.image.unreadable')]);
        }

        return $image;
    }

    /** @return array{bytes: string, extension: string, mime: string} */
    private function encode(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            $image = $scaled instanceof GdImage ? $scaled : $image;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        ob_start();

        if (function_exists('imagewebp')) {
            imagewebp($image, null, 80);
            $result = ['extension' => 'webp', 'mime' => 'image/webp'];
        } else {
            // JPEG has no transparency: flatten onto white first.
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($flat, null, 82);
            $result = ['extension' => 'jpg', 'mime' => 'image/jpeg'];
        }

        return ['bytes' => (string) ob_get_clean()] + $result;
    }
}
