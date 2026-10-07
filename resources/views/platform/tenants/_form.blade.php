@php
	$value = fn (string $key, $default = '') => old($key, $default);
	$overrides = $overrides ?? [];
	$extra = old('override_features', $overrides['features'] ?? []);
@endphp
<div class="card">
	<div class="card-body">
		<div class="row">
			<div class="col-md-6 mb-3">
				<label class="form-label">{{ __('platform.name') }}</label>
				<input type="text" name="name" class="form-control" value="{{ $value('name', $tenant->name ?? '') }}" required>
			</div>
			<div class="col-md-6 mb-3">
				<label class="form-label">{{ __('platform.slug') }}</label>
				@if ($tenant ?? null)
					<input type="text" class="form-control" value="{{ $tenant->slug }}" disabled>
				@else
					<input type="text" name="slug" class="form-control" value="{{ $value('slug') }}" required>
					<small class="text-muted">{{ __('platform.slug_hint') }}</small>
				@endif
			</div>
			<div class="col-md-6 mb-3">
				<label class="form-label">{{ __('platform.domain') }}</label>
				<input type="text" name="domain" class="form-control" value="{{ $value('domain', $tenant->domain ?? '') }}">
			</div>
			<div class="col-md-2 mb-3">
				<label class="form-label">{{ __('platform.plan') }}</label>
				<select name="plan" class="form-select">
					@foreach ($plans as $plan)
						<option value="{{ $plan }}" @selected($value('plan', $tenant->plan ?? config('plans.default')) === $plan)>{{ __('platform.plans.'.$plan) }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-2 mb-3">
				<label class="form-label">{{ __('platform.status') }}</label>
				<select name="status" class="form-select">
					@foreach (['active', 'trial', 'suspended'] as $status)
						<option value="{{ $status }}" @selected($value('status', $tenant->status ?? 'active') === $status)>{{ __('platform.statuses.'.$status) }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-2 mb-3">
				<label class="form-label">{{ __('platform.paid_until') }}</label>
				<input type="date" name="paid_until" class="form-control" value="{{ $value('paid_until', ($tenant ?? null)?->paid_until?->format('Y-m-d') ?? '') }}">
			</div>
		</div>
	</div>
</div>

@unless ($tenant ?? null)
	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('platform.owner') }}</h5></div>
		<div class="card-body">
			<div class="row">
				<div class="col-md-4 mb-3">
					<label class="form-label">{{ __('platform.owner_name') }}</label>
					<input type="text" name="owner_name" class="form-control" value="{{ $value('owner_name') }}" required>
				</div>
				<div class="col-md-4 mb-3">
					<label class="form-label">{{ __('platform.owner_email') }}</label>
					<input type="email" name="owner_email" class="form-control" value="{{ $value('owner_email') }}" required>
				</div>
				<div class="col-md-4 mb-3">
					<label class="form-label">{{ __('platform.owner_password') }}</label>
					<input type="password" name="owner_password" class="form-control" minlength="8" required autocomplete="new-password">
				</div>
			</div>
		</div>
	</div>
@endunless

<div class="card">
	<div class="card-header">
		<h5 class="mb-0">{{ __('platform.overrides') }}</h5>
		<small class="text-muted">{{ __('platform.overrides_hint') }}</small>
	</div>
	<div class="card-body">
		<div class="mb-3">
			<label class="form-label d-block">{{ __('platform.extra_features') }}</label>
			@foreach (config('plans.features') as $feature)
				<div class="form-check form-check-inline">
					<input class="form-check-input" type="checkbox" name="override_features[]" id="of-{{ $feature }}" value="{{ $feature }}" @checked(in_array($feature, (array) $extra, true))>
					<label class="form-check-label" for="of-{{ $feature }}">{{ __('plans.features.'.$feature) }}</label>
				</div>
			@endforeach
		</div>
		<div class="row">
			<div class="col-md-3 mb-3">
				<label class="form-label">{{ __('platform.limit_users') }}</label>
				<input type="number" min="1" name="limit_users" class="form-control" value="{{ $value('limit_users', $overrides['limits']['users'] ?? '') }}">
			</div>
			<div class="col-md-3 mb-3">
				<label class="form-label">{{ __('platform.limit_warehouses') }}</label>
				<input type="number" min="1" name="limit_warehouses" class="form-control" value="{{ $value('limit_warehouses', $overrides['limits']['warehouses'] ?? '') }}">
			</div>
		</div>
	</div>
</div>

<div class="d-flex justify-content-end gap-2 mb-4">
	<a href="{{ route('platform.tenants.index') }}" class="btn btn-white">{{ __('platform.back') }}</a>
	<button type="submit" class="btn btn-primary">{{ __('platform.save') }}</button>
</div>
