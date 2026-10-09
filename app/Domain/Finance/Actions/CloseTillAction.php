<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\TillClosing;
use App\Domain\Finance\Services\TillSummary;
use App\Models\User;
use App\Support\TenantSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes a cashier's till: freezes what the system expected against what they counted. The figures are worked
 * out here at the moment of closing (not taken from the form), and the client's idempotency key makes a double
 * submit harmless.
 */
class CloseTillAction
{
    public function __construct(private readonly TillSummary $summary) {}

    /**
     * @param  array{idempotency_key: string, counted_cash: int, float_kept: int, note?: string|null}  $data  money in minor units
     * @return array{closing: TillClosing, created: bool}
     */
    public function handle(User $user, array $data): array
    {
        if ($existing = $this->findExisting($data['idempotency_key'])) {
            return ['closing' => $existing, 'created' => false];
        }

        if ($data['float_kept'] > $data['counted_cash']) {
            throw ValidationException::withMessages(['float_kept' => __('till.errors.float_too_big')]);
        }

        try {
            $closing = DB::transaction(function () use ($user, $data): TillClosing {
                $figures = $this->summary->for($user);

                return TillClosing::create([
                    'user_id' => $user->getKey(),
                    'idempotency_key' => $data['idempotency_key'],
                    'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
                    'period_start' => $figures['start'],
                    'period_end' => $figures['end'],
                    'opening_float' => $figures['opening_float'],
                    'cash_sales' => $figures['cash_sales'],
                    'cash_refunds' => $figures['cash_refunds'],
                    'expected_cash' => $figures['expected_cash'],
                    'counted_cash' => $data['counted_cash'],
                    'difference' => $data['counted_cash'] - $figures['expected_cash'],
                    'float_kept' => $data['float_kept'],
                    'sales_count' => $figures['sales_count'],
                    'sales_total' => $figures['sales_total'],
                    'by_method' => $figures['by_method'],
                    'note' => $data['note'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($existing = $this->findExisting($data['idempotency_key'])) {
                return ['closing' => $existing, 'created' => false];
            }

            throw $e;
        }

        return ['closing' => $closing, 'created' => true];
    }

    private function findExisting(string $key): ?TillClosing
    {
        return TillClosing::query()->where('idempotency_key', $key)->first();
    }
}
