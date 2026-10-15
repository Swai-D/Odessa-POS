<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Sales\Actions\HandleAzamPayCallbackAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AzamPay's result callback. Public on purpose: the shop is named in the address (each shop gives AzamPay its own
 * address) and the body is only believed after its signature is verified, so a forged call changes nothing.
 */
class AzamPayCallbackController extends Controller
{
    public function __invoke(Request $request, string $tenant, TenantContext $context, HandleAzamPayCallbackAction $action): JsonResponse
    {
        $shop = Tenant::query()->whereKey($tenant)->first();

        if ($shop === null) {
            return response()->json(['status' => HandleAzamPayCallbackAction::UNKNOWN], 404);
        }

        $outcome = $context->run($shop, fn (): string => $action->handle($request->json()->all()));

        return response()->json(['status' => $outcome], $outcome === HandleAzamPayCallbackAction::INVALID_SIGNATURE ? 401 : 200);
    }
}
