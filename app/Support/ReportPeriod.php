<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** The date range of a report page: the validated `from` and `to`, defaulting to the last 30 days, at most a year. */
class ReportPeriod
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function fromRequest(Request $request): array
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->is_super_admin || $user->can('reports.view')), 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($data['to']) ? Carbon::parse($data['to']) : Carbon::today();
        $from = isset($data['from']) ? Carbon::parse($data['from']) : $to->copy()->subDays(29);

        // Keep the queries cheap.
        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365);
        }

        return [$from, $to];
    }
}
