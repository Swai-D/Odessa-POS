@extends('layouts.app')

@section('title', ($plan->exists ? __('platform.plan_edit') : __('platform.plan_new')).' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header"><div class="page-title"><h4 class="fw-bold">{{ $plan->exists ? __('platform.plan_edit') : __('platform.plan_new') }}</h4></div></div>
	@include('partials.flash')
	<form method="POST" action="{{ $plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store') }}">
		@csrf
		@if ($plan->exists) @method('PUT') @endif
		<div class="card"><div class="card-body">
			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label" for="plan-name">{{ __('platform.plan_name') }}</label>
					<input id="plan-name" name="name" class="form-control" maxlength="100" value="{{ old('name', $plan->name) }}" required>
				</div>
				<div class="col-md-3 mb-3">
					<label class="form-label" for="plan-code">{{ __('platform.plan_code') }}</label>
					<input id="plan-code" name="code" class="form-control" maxlength="40" value="{{ old('code', $plan->code) }}" @disabled($plan->exists) @required(! $plan->exists)>
				</div>
				<div class="col-md-3 mb-3">
					<label class="form-label" for="plan-sort">{{ __('platform.sort_order') }}</label>
					<input id="plan-sort" type="number" min="0" max="65535" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" required>
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="plan-monthly">{{ __('platform.monthly_price') }} (TZS)</label>
					<input id="plan-monthly" type="number" min="0.01" step="0.01" name="monthly_price" class="form-control" value="{{ old('monthly_price', $plan->monthly_price === null ? '' : \App\Support\Money::toMajor($plan->monthly_price)) }}" required>
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label" for="plan-annual">{{ __('platform.annual_price') }} (TZS)</label>
					<input id="plan-annual" type="number" min="0.01" step="0.01" name="annual_price" class="form-control" value="{{ old('annual_price', $plan->annual_price === null ? '' : \App\Support\Money::toMajor($plan->annual_price)) }}" required>
				</div>
				<div class="col-md-3 mb-3">
					<label class="form-label" for="plan-users">{{ __('platform.limit_users') }}</label>
					<input id="plan-users" type="number" min="1" name="limit_users" class="form-control" value="{{ old('limit_users', $plan->limits['users'] ?? '') }}">
				</div>
				<div class="col-md-3 mb-3">
					<label class="form-label" for="plan-warehouses">{{ __('platform.limit_warehouses') }}</label>
					<input id="plan-warehouses" type="number" min="1" name="limit_warehouses" class="form-control" value="{{ old('limit_warehouses', $plan->limits['warehouses'] ?? '') }}">
				</div>
				<div class="col-md-3 mb-3">
					<label class="form-label" for="plan-active">{{ __('platform.status') }}</label>
					<input type="hidden" name="is_active" value="0">
					<div class="form-check form-switch mt-2"><input id="plan-active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $plan->is_active ?? true))><label class="form-check-label" for="plan-active">{{ __('platform.active') }}</label></div>
				</div>
			</div>
		</div></div>

		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('platform.plan_features') }}</h5></div>
			<div class="card-body">
				<div class="form-check mb-3">
					<input id="plan-all-features" type="checkbox" name="features[]" value="*" class="form-check-input" @checked(in_array('*', old('features', $plan->features ?? []), true))>
					<label class="form-check-label" for="plan-all-features">{{ __('platform.all_features') }}</label>
				</div>
				<div class="row">
					@foreach ($features as $feature)
						<div class="col-md-4 col-sm-6 mb-2"><div class="form-check">
							<input id="feature-{{ $feature }}" type="checkbox" name="features[]" value="{{ $feature }}" class="form-check-input" @checked(in_array($feature, old('features', $plan->features ?? []), true))>
							<label class="form-check-label" for="feature-{{ $feature }}">{{ __('plans.features.'.$feature) }}</label>
						</div></div>
					@endforeach
				</div>
				<p class="text-muted mb-0">{{ __('platform.unlimited_hint') }}</p>
			</div>
		</div>
		<div class="d-flex justify-content-end gap-2 mb-4">
			<a href="{{ route('platform.plans.index') }}" class="btn btn-white">{{ __('platform.back') }}</a>
			<button type="submit" class="btn btn-primary">{{ __('platform.save') }}</button>
		</div>
	</form>
</div>
@endsection