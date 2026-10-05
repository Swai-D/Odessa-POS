<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records a later payment against an unpaid or partially paid sale (customer debt settlement). */
class RecordPaymentAction
{
    /** @param  array{method: string, amount: int|string, reference?: string|null}  $data */
    public function handle(Sale $sale, array $data, ?User $user): Sale
    {
        return DB::transaction(function () use ($sale, $data, $user): Sale {
            /** @var Sale $locked */
            $locked = Sale::query()->lockForUpdate()->findOrFail($sale->getKey());
            $amount = (int) $data['amount'];

            if ($locked->balance_due <= 0) {
                throw ValidationException::withMessages(['amount' => __('pos.errors.already_settled')]);
            }

            if ($amount > $locked->balance_due) {
                throw ValidationException::withMessages(['amount' => __('pos.errors.exceeds_balance')]);
            }

            $locked->payments()->create([
                'user_id' => $user?->getKey(),
                'method' => $data['method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);

            $paid = $locked->amount_paid + $amount;
            $balance = $locked->total - $paid;

            $locked->update([
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'payment_status' => $balance === 0 ? Sale::PAID : Sale::PARTIAL,
            ]);

            return $locked;
        });
    }
}
