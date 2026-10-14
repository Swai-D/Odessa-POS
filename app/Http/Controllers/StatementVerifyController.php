<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Models\BusinessStatement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public page where a bank or lender checks a business statement by its code. It sits outside any shop, so it
 * looks the code up across all shops on purpose (the code is unique and unguessable) and shows only the headline
 * figures that are printed on the paper. Lookups are throttled in the route.
 */
class StatementVerifyController extends Controller
{
    public function show(Request $request): View
    {
        $input = trim((string) $request->query('code', ''));
        $statement = null;

        if ($input !== '') {
            $statement = BusinessStatement::query()
                ->withoutGlobalScope('tenant')
                ->where('code', BusinessStatement::normalizeCode($input))
                ->first();
        }

        return view('business-statements.verify', [
            'input' => $input,
            'statement' => $statement,
            'intact' => $statement?->isIntact(),
        ]);
    }
}
