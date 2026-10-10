@extends('layouts.app')

@section('title', __('settings.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('settings.title') }}</h4>
				<h6>{{ __('settings.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			@if (\App\Support\Plans::current()->allowsAny('printer', 'mobile_money', 'fiscal'))
			<a href="{{ route('settings.integrations') }}" class="btn btn-white"><i class="ti ti-plug-connected me-1"></i>{{ __('integrations.title') }}</a>
			@endif
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('settings.update') }}">
		@csrf
		@method('PUT')

		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('settings.business') }}</h5></div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-6 mb-3">
						<label class="form-label" for="business_name">{{ __('settings.business_name') }}<span class="text-danger ms-1">*</span></label>
						<input type="text" class="form-control" id="business_name" name="business_name" value="{{ old('business_name', $businessName) }}" maxlength="120" required>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label" for="currency">{{ __('settings.currency') }}<span class="text-danger ms-1">*</span></label>
						<select class="form-select" id="currency" name="currency">
							@foreach ($currencies as $code)
								<option value="{{ $code }}" @selected(old('currency', $currency) === $code)>{{ $code }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-12 mb-3">
						<label class="form-label" for="receipt_footer">{{ __('settings.receipt_footer') }}</label>
						<input type="text" class="form-control" id="receipt_footer" name="receipt_footer" value="{{ old('receipt_footer', $receiptFooter) }}" maxlength="300">
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h5 class="mb-0">{{ __('settings.features') }}</h5>
				<small class="text-muted">{{ __('settings.features_hint') }}</small>
			</div>
			<div class="card-body">
				<div class="row">
					@foreach ($features as $key => $enabled)
						<div class="col-md-6 mb-3">
							<div class="form-check form-switch">
								<input type="hidden" name="features[{{ $key }}]" value="0">
								<input class="form-check-input" type="checkbox" role="switch" id="feature-{{ $key }}" name="features[{{ $key }}]" value="1" @checked(old('features.'.$key, $enabled))>
								<label class="form-check-label" for="feature-{{ $key }}">{{ __('settings.feature_names.'.$key) }}</label>
							</div>
						</div>
					@endforeach
				</div>
			</div>
		</div>

		<div class="text-end mb-4">
			<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
		</div>
	</form>
</div>
@endsection
