<?php

namespace App\Domain\Finance\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A business statement a shop can hand to a bank or lender: its sales, profit and position over recent months,
 * frozen at the moment it was issued, with a code anyone can check on the public verify page.
 */
class BusinessStatement extends Model
{
    use BelongsToTenant;

    public const MONTH_CHOICES = [3, 6, 12];

    protected $fillable = [
        'user_id', 'number', 'code', 'shop_name', 'currency', 'locale', 'months', 'period_from', 'period_to',
        'data', 'checksum', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
            'data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Keyed hash over everything that identifies the statement and its figures. */
    public function computeChecksum(): string
    {
        $payload = [
            'number' => $this->number,
            'tenant' => $this->tenant_id,
            'code' => $this->code,
            'shop' => $this->shop_name,
            'from' => Carbon::parse($this->period_from)->format('Y-m-d'),
            'to' => Carbon::parse($this->period_to)->format('Y-m-d'),
            'at' => Carbon::parse($this->generated_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'data' => $this->data,
        ];

        return hash_hmac('sha256', self::canonical($payload), (string) config('app.key'));
    }

    public function isIntact(): bool
    {
        return hash_equals($this->checksum, $this->computeChecksum());
    }

    /**
     * Stable JSON: associative keys sorted at every level, so the hash does not depend on how the database
     * returns the stored figures.
     *
     * @param  array<mixed>  $value
     */
    public static function canonical(array $value): string
    {
        return (string) json_encode(self::sorted($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sorted(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sorted($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** The code as shown on paper, e.g. K7M2-9QXA-4TBE. */
    public static function normalizeCode(string $input): string
    {
        $clean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));

        return implode('-', str_split($clean, 4));
    }
}
