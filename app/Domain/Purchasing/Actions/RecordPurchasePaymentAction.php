<?php

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Records a later payment to the supplier against a purchase that is not fully paid. */
class RecordPurchasePaymentAction
{
    /** @param  array{method: string, amount: int, reference?: string|null}  $data  amount in minor units */
    public function handle(Purchase $purchase, array $data, ?User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $user): Purchase {
            /** @var Purchase $locked */
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->getKey());
            $amount = $data['amount'];

            if ($locked->balance_due <= 0) {
                throw ValidationException::withMessages(['amount' => __('purchases.errors.already_settled')]);
            }

            if ($amount > $locked->balance_due) {
                throw ValidationException::withMessages(['amount' => __('purchases.errors.exceeds_balance')]);
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
                'payment_status' => $balance <= 0 ? Purchase::PAID : Purchase::PARTIAL,
            ]);

            return $locked;
        });
    }
}
