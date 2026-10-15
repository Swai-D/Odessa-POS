<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\Actions\RequestMobileMoneyPaymentAction;
use App\Domain\Sales\Models\PaymentRequest;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobileMoneyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** The till's side of mobile money: send the prompt, then ask how it went. */
class MobileMoneyController extends Controller
{
    public function store(MobileMoneyRequest $request, RequestMobileMoneyPaymentAction $action): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        return response()->json($this->payload($action->handle($request->validated(), $request->user())), 201);
    }

    public function show(PaymentRequest $paymentRequest): JsonResponse
    {
        Gate::authorize('create', Sale::class);

        // A prompt nobody answered stops waiting after a few minutes. A late success callback can still change it.
        if ($paymentRequest->status === PaymentRequest::PENDING && $paymentRequest->expires_at->isPast()) {
            $paymentRequest->update(['status' => PaymentRequest::EXPIRED, 'completed_at' => now()]);
        }

        return response()->json($this->payload($paymentRequest));
    }

    /** @return array<string, mixed> */
    private function payload(PaymentRequest $request): array
    {
        return [
            'id' => $request->getKey(),
            'status' => $request->status,
            'amount' => $request->amount,
            'provider' => $request->provider,
            'phone' => $request->phone,
            'reason' => $request->status === PaymentRequest::FAILED ? $request->failure_reason : null,
            'expires_at' => $request->expires_at->toIso8601String(),
            'used' => $request->sale_id !== null,
        ];
    }
}
