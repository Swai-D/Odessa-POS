<?php

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\PaymentRequest;
use App\Domain\Sales\Services\AzamPayClient;
use App\Domain\Sales\Services\AzamPayException;
use Illuminate\Support\Facades\DB;

/**
 * Applies AzamPay's result callback to a payment request. Must run inside the shop's tenant context.
 *
 * Nothing in the body is trusted until its RSA signature checks out. A success is accepted only when the amount matches
 * what was asked, a request that already succeeded is never changed, and a late success (after the request expired or
 * was marked failed) is still honoured, because the customer's money has moved.
 */
class HandleAzamPayCallbackAction
{
    public const APPLIED = 'applied';

    public const DUPLICATE = 'duplicate';

    public const UNKNOWN = 'unknown';

    public const INVALID_SIGNATURE = 'invalid_signature';

    /** @param  array<string, mixed>  $payload  the callback body */
    public function handle(array $payload): string
    {
        $client = AzamPayClient::forCurrentTenant();
        $utilityRef = (string) ($payload['utilityref'] ?? '');
        $externalRef = (string) ($payload['externalreference'] ?? '');
        $status = (string) ($payload['transactionstatus'] ?? '');
        $operator = (string) ($payload['operator'] ?? '');

        if ($client === null) {
            return self::UNKNOWN;
        }

        try {
            $valid = $client->verifyCallback($utilityRef, $externalRef, $status, $operator, (string) ($payload['signature'] ?? ''));
        } catch (AzamPayException $e) {
            report($e);
            $valid = false;
        }

        if (! $valid) {
            return self::INVALID_SIGNATURE;
        }

        return DB::transaction(function () use ($payload, $utilityRef, $externalRef, $status): string {
            $request = PaymentRequest::query()->lockForUpdate()->where('reference', $utilityRef)->first()
                ?? PaymentRequest::query()->lockForUpdate()->where('provider_reference', $externalRef)->first();

            if ($request === null) {
                return self::UNKNOWN;
            }

            if ($request->status === PaymentRequest::SUCCEEDED) {
                return self::DUPLICATE;
            }

            // Keep what the provider told us for support, without the credentials it echoes back.
            $kept = array_diff_key($payload, array_flip(['password', 'user', 'signature']));
            $succeeded = strtolower($status) === 'success';
            $paid = (int) round((float) ($payload['amount'] ?? 0) * 100);

            if ($succeeded && $paid !== $request->amount) {
                $request->update(['status' => PaymentRequest::FAILED, 'failure_reason' => 'amount_mismatch', 'callback' => $kept, 'completed_at' => now()]);

                return self::APPLIED;
            }

            $request->update([
                'status' => $succeeded ? PaymentRequest::SUCCEEDED : PaymentRequest::FAILED,
                'failure_reason' => $succeeded ? null : mb_substr((string) ($payload['message'] ?? 'failed'), 0, 250),
                'provider_reference' => $request->provider_reference ?? ($externalRef !== '' ? $externalRef : null),
                'mno_reference' => ($payload['mnoreference'] ?? '') !== '' ? (string) $payload['mnoreference'] : null,
                'callback' => $kept,
                'completed_at' => now(),
            ]);

            return self::APPLIED;
        });
    }
}
