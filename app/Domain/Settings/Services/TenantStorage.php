<?php

namespace App\Domain\Settings\Services;

use App\Models\StoredFile;
use App\Support\Plans;
use Illuminate\Support\Facades\Storage;

/**
 * How much of the shop's storage allowance is used. The allowance (`storage_mb`) comes from the plan, or from a
 * per-shop override; a plan without the limit is unlimited. Files live on the disk named in `pos.uploads_disk`,
 * so moving from a Railway volume to S3/R2 is an environment change.
 */
class TenantStorage
{
    public function diskName(): string
    {
        return (string) config('pos.uploads_disk');
    }

    /** Bytes in use by the current shop. */
    public function used(): int
    {
        return (int) StoredFile::query()->sum('size');
    }

    /** Allowance in bytes; null means unlimited. */
    public function limit(): ?int
    {
        $megabytes = Plans::current()->limit('storage_mb');

        return $megabytes === null ? null : $megabytes * 1024 * 1024;
    }

    /** Whether `$bytes` more would still fit, after giving back `$releasing` (a file about to be replaced). */
    public function fits(int $bytes, int $releasing = 0): bool
    {
        $limit = $this->limit();

        return $limit === null || ($this->used() - $releasing + $bytes) <= $limit;
    }

    public function url(StoredFile $file): string
    {
        return Storage::disk($file->disk)->url($file->path);
    }

    /** Remove the stored bytes and the record. */
    public function delete(StoredFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }
}
