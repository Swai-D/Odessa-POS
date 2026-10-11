<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Settings\Models\AuditLog;
use App\Http\Controllers\Controller;
use App\Support\Search;
use App\Support\TenantSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** The shop's history, newest first, with filters by date, kind of record, event and person. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', 'max:60'],
            'event' => ['nullable', 'string', 'max:40'],
            'user' => ['nullable', 'integer'],
        ]);

        $logs = AuditLog::query()
            ->when(isset($data['from']), fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($data['from'])->startOfDay()))
            ->when(isset($data['to']), fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($data['to'])->endOfDay()))
            ->when(! empty($data['type']), fn (Builder $q) => $q->where('subject_type', $data['type']))
            ->when(! empty($data['event']), fn (Builder $q) => $q->where('event', $data['event']))
            ->when(! empty($data['user']), fn (Builder $q) => $q->where('user_id', $data['user']))
            ->tap(fn (Builder $q) => Search::apply($q, $request->query('q'), ['subject_label', 'user_name']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('audit.index', [
            'logs' => $logs->paginate(30)->withQueryString(),
            'types' => AuditLog::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'people' => AuditLog::query()->whereNotNull('user_id')->distinct()->orderBy('user_name')->pluck('user_name', 'user_id'),
            'filters' => $data,
            'currency' => (string) (new TenantSettings)->get('currency', config('pos.default_currency')),
        ]);
    }
}
