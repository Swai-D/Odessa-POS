<?php

namespace App\Http\Controllers;

use App\Support\ErrorTracking\SentryReporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Receives JavaScript errors from the browser and forwards them to error tracking. Nothing is stored. */
class ClientErrorController extends Controller
{
    public function __invoke(Request $request, SentryReporter $reporter): JsonResponse
    {
        if (config('errors.browser')) {
            $data = $request->validate([
                'message' => ['required', 'string', 'max:500'],
                'source' => ['nullable', 'string', 'max:300'],
                'line' => ['nullable', 'integer'],
                'column' => ['nullable', 'integer'],
                'stack' => ['nullable', 'string', 'max:4000'],
                'page' => ['nullable', 'string', 'max:300'],
            ]);
            $reporter->captureBrowser($data);
        }

        return response()->json(['ok' => true]);
    }
}
