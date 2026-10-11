<?php

namespace App\Http\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * For uptime monitors. Unlike `/up` (which only proves the app boots, and is what Railway restarts on) this also
 * checks the database, so a monitor can alert when Postgres is unreachable. It reveals nothing else and uses no
 * session, so it still answers while the database is down.
 */
class HealthController extends Controller
{
    public function __invoke(ConnectionInterface $db): JsonResponse
    {
        try {
            $db->select('select 1');
            $database = true;
        } catch (Throwable) {
            $database = false;
        }

        return response()->json(
            ['status' => $database ? 'ok' : 'degraded', 'database' => $database ? 'up' : 'down'],
            $database ? 200 : 503,
            ['Cache-Control' => 'no-store'],
        );
    }
}
