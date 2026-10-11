@extends('layouts.app')

@section('title', __('audit.title').' - Odessa POS')

@section('content')
@php
	$label = fn (string $key, string $group, ?string $fallback = null) => \Illuminate\Support\Facades\Lang::has("audit.$group.$key") ? __("audit.$group.$key") : ($fallback ?? $key);
	$show = function (string $field, mixed $value) use ($currency) {
		if ($value === null || $value === '') {
			return '—';
		}
		if (is_bool($value)) {
			return $value ? __('app.yes') : __('app.no');
		}
		if (in_array($field, \App\Domain\Settings\Models\AuditLog::MONEY_FIELDS, true) && is_numeric($value)) {
			return \App\Support\Money::format((int) $value, $currency);
		}

		return (string) $value;
	};
@endphp
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('audit.title') }}</h4>
				<h6>{{ __('audit.hint') }}</h6>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="card-header">
			<form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end w-100">
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('audit.from') }}</label>
					<input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
				</div>
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('audit.to') }}</label>
					<input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
				</div>
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('audit.record') }}</label>
					<select name="type" class="form-select">
						<option value="">{{ __('audit.all') }}</option>
						@foreach ($types as $type)
							<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $label($type, 'types') }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('audit.event') }}</label>
					<select name="event" class="form-select">
						<option value="">{{ __('audit.all') }}</option>
						@foreach ($events as $event)
							<option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $label($event, 'events') }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('audit.user') }}</label>
					<select name="user" class="form-select">
						<option value="">{{ __('audit.all') }}</option>
						@foreach ($people as $id => $name)
							<option value="{{ $id }}" @selected((string) ($filters['user'] ?? '') === (string) $id)>{{ $name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2 col-6">
					<label class="form-label mb-1">{{ __('app.search') }}</label>
					<input type="search" name="q" class="form-control" value="{{ request('q') }}">
				</div>
				<div class="col-12">
					<button type="submit" class="btn btn-primary">{{ __('audit.filter') }}</button>
					<a href="{{ route('audit.index') }}" class="btn btn-secondary ms-1">{{ __('audit.reset') }}</a>
				</div>
			</form>
			@if ($errors->any())
				<div class="text-danger fs-13 mt-2">{{ $errors->first() }}</div>
			@endif
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>{{ __('audit.when') }}</th>
							<th>{{ __('audit.user') }}</th>
							<th>{{ __('audit.event') }}</th>
							<th>{{ __('audit.record') }}</th>
							<th>{{ __('audit.details') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($logs as $log)
							<tr>
								<td class="text-nowrap">{{ $log->created_at->format('Y-m-d H:i') }}</td>
								<td>{{ $log->user_name ?? __('audit.system') }}</td>
								<td><span class="badge {{ $log->event === 'deleted' ? 'badge-soft-danger' : ($log->event === 'created' ? 'badge-soft-success' : 'badge-soft-info') }}">{{ $label($log->event, 'events', $log->event) }}</span></td>
								<td>{{ $label($log->subject_type, 'types') }} <span class="text-muted">{{ $log->subject_label }}</span></td>
								<td>
									@if (! empty($log->changes))
										<details>
											<summary class="text-primary" style="cursor:pointer">{{ __('audit.fields_count', ['count' => count($log->changes)]) }}</summary>
											<ul class="list-unstyled mb-0 mt-1 fs-13">
												@foreach ($log->changes as $field => $change)
													<li>
														<span class="fw-semibold">{{ $label($field, 'fields', \Illuminate\Support\Str::headline($field)) }}:</span>
														@if ($log->event === 'created')
															{{ $show($field, $change['new'] ?? null) }}
														@else
															<span class="text-muted">{{ $show($field, $change['old'] ?? null) }}</span> → {{ $show($field, $change['new'] ?? null) }}
														@endif
													</li>
												@endforeach
											</ul>
										</details>
									@else
										<span class="text-muted">—</span>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-center text-muted py-4">{{ __('audit.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $logs])
		</div>
	</div>
</div>
@endsection
