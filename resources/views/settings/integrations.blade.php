@extends('layouts.app')

@section('title', __('integrations.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('integrations.title') }}</h4>
				<h6>{{ __('integrations.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('settings.index') }}" class="btn btn-white">{{ __('settings.title') }}</a>
		</div>
	</div>

	@include('partials.flash')

	@foreach ($channels as $channel => $info)
		<div class="card">
			<div class="card-header">
				<h5 class="mb-0">{{ __('integrations.channels.'.$channel.'.title') }}</h5>
				<small class="text-muted">{{ __('integrations.channels.'.$channel.'.hint') }}</small>
			</div>
			<div class="card-body">
				@if ($info['locked'])
					<p class="text-muted mb-0"><i class="ti ti-lock me-1"></i>{{ __('plans.locked') }}@if ($info['needed']) · {{ __('plans.available_from', ['plan' => __('platform.plans.'.$info['needed'])]) }}@endif</p>
				@elseif ($info['drivers'] === [])
					<p class="text-muted mb-0">{{ __('integrations.no_providers') }}</p>
				@else
					<form method="POST" action="{{ route('settings.integrations.update', $channel) }}" class="js-integration">
						@csrf
						@method('PUT')

						<div class="form-check form-switch mb-3">
							<input type="hidden" name="enabled" value="0">
							<input class="form-check-input" type="checkbox" role="switch" id="enabled-{{ $channel }}" name="enabled" value="1" @checked($info['enabled'])>
							<label class="form-check-label" for="enabled-{{ $channel }}">{{ __('integrations.enabled') }}</label>
						</div>

						<div class="mb-3">
							<label class="form-label" for="driver-{{ $channel }}">{{ __('integrations.provider') }}</label>
							<select class="form-select js-driver" id="driver-{{ $channel }}" name="driver">
								<option value=""></option>
								@foreach ($info['drivers'] as $key => $driver)
									<option value="{{ $key }}" @selected($info['driver'] === $key)>{{ __($driver['label']) }}</option>
								@endforeach
							</select>
						</div>

						@foreach ($info['drivers'] as $key => $driver)
							<div class="js-driver-fields row" data-driver="{{ $key }}" style="display: none;">
								@foreach ($driver['fields'] as $field)
									@php
										$name = $field['name'];
										$isSecret = $field['secret'] ?? false;
										$value = $driver['values'][$name] ?? ($field['default'] ?? '');
									@endphp
									<div class="col-md-6 mb-3">
										<label class="form-label">{{ __($field['label']) }}@if ($field['required'] ?? false)<span class="text-danger ms-1">*</span>@endif</label>
										@if ($field['type'] === 'select')
											<select class="form-select" name="config[{{ $name }}]" disabled>
												@foreach ($field['options'] as $optionValue => $optionLabel)
													<option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ \Illuminate\Support\Facades\Lang::has($optionLabel) ? __($optionLabel) : $optionLabel }}</option>
												@endforeach
											</select>
										@else
											<input
												type="{{ $isSecret || $field['type'] === 'password' ? 'password' : ($field['type'] === 'number' ? 'number' : 'text') }}"
												class="form-control" name="config[{{ $name }}]" disabled
												@if ($isSecret) value="" autocomplete="new-password" placeholder="{{ in_array($name, $driver['savedSecrets'], true) ? __('integrations.secret_saved') : '' }}"
												@else value="{{ $value }}" @endif
												@if ($field['type'] === 'number') min="1" max="99" @endif
											>
										@endif
									</div>
								@endforeach
							</div>
						@endforeach

						<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
					</form>
				@endif
			</div>
		</div>
	@endforeach
</div>
@endsection

@push('extra-js')
<script>
	document.querySelectorAll('.js-integration').forEach(function (form) {
		var select = form.querySelector('.js-driver');

		function sync() {
			form.querySelectorAll('.js-driver-fields').forEach(function (block) {
				var active = block.dataset.driver === select.value;
				block.style.display = active ? '' : 'none';
				// Only the chosen driver's fields are submitted.
				block.querySelectorAll('input, select').forEach(function (input) { input.disabled = !active; });
			});
		}

		select.addEventListener('change', sync);
		sync();
	});
</script>
@endpush
