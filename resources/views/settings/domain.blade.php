@extends('layouts.app')

@section('title', __('domain.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('domain.title') }}</h4>
				<h6>{{ __('domain.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('settings.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left me-1"></i>{{ __('settings.title') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body">
			<div class="mb-3">
				<div class="text-muted">{{ __('domain.shared_link') }}</div>
				<div class="fw-medium">{{ $sharedLink }}</div>
			</div>

			@if ($tenant->domain_pending && $steps)
				<h5 class="mb-1">{{ __('domain.steps_title') }}</h5>
				<p class="text-muted">{{ __('domain.steps_hint', ['domain' => $tenant->domain_pending]) }}</p>
				<div class="table-responsive mb-3">
					<table class="table">
						<thead class="thead-light"><tr><th></th><th>{{ __('domain.type') }}</th><th>{{ __('domain.name') }}</th><th>{{ __('domain.value') }}</th></tr></thead>
						<tbody>
							<tr><td>{{ __('domain.step_txt') }}</td><td>TXT</td><td><code>{{ $steps['txt_host'] }}</code></td><td><code>{{ $steps['txt_value'] }}</code></td></tr>
							<tr><td>{{ __('domain.step_cname') }}</td><td>CNAME</td><td><code>{{ $steps['cname_host'] }}</code></td><td><code>{{ $steps['target'] }}</code></td></tr>
						</tbody>
					</table>
				</div>
				<p class="text-muted">{{ __('domain.after') }}</p>
				<div class="d-flex gap-2">
					<form method="POST" action="{{ route('settings.domain.check') }}">@csrf<button type="submit" class="btn btn-primary"><i class="ti ti-refresh me-1"></i>{{ __('domain.check') }}</button></form>
					<form method="POST" action="{{ route('settings.domain.destroy') }}">@csrf @method('DELETE')<button type="submit" class="btn btn-secondary">{{ __('domain.cancel') }}</button></form>
				</div>
			@elseif ($tenant->domain)
				<div class="mb-3">
					<div class="text-muted">{{ __('domain.current') }}</div>
					<div class="fw-bold fs-5">{{ $tenant->domain }}</div>
					@if ($tenant->domain_routed_at)
						<span class="badge bg-success">{{ __('domain.status_working') }}</span>
					@else
						<span class="badge bg-warning">{{ __('domain.status_waiting') }}</span>
					@endif
				</div>
				@if ($tenant->domain_verified_at === null)
					<p class="text-muted">{{ __('domain.set_by_platform') }}</p>
				@else
					<div class="d-flex gap-2">
						<form method="POST" action="{{ route('settings.domain.check') }}">@csrf<button type="submit" class="btn btn-primary"><i class="ti ti-refresh me-1"></i>{{ __('domain.recheck') }}</button></form>
						<form method="POST" action="{{ route('settings.domain.destroy') }}" onsubmit="return confirm(@js(__('domain.remove_confirm')));">@csrf @method('DELETE')<button type="submit" class="btn btn-danger">{{ __('domain.remove') }}</button></form>
					</div>
				@endif
			@else
				<p class="text-muted">{{ __('domain.none') }}</p>
				<form method="POST" action="{{ route('settings.domain.store') }}" class="row g-2">
					@csrf
					<div class="col-md-6">
						<label class="form-label" for="domain">{{ __('domain.domain') }}</label>
						<input type="text" class="form-control" id="domain" name="domain" value="{{ old('domain') }}" placeholder="pos.yourshop.co.tz" maxlength="190" required>
						<small class="text-muted">{{ __('domain.domain_hint') }}</small>
					</div>
					<div class="col-12"><button type="submit" class="btn btn-primary">{{ __('domain.save') }}</button></div>
				</form>
			@endif
		</div>
	</div>
</div>
@endsection
