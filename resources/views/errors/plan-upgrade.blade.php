@extends('layouts.app')

@section('title', __('plans.upgrade_title'))
@section('content')
@php
	$needed = \App\Support\Plans::cheapestPlanFor($feature);
	$contact = array_filter((array) config('plans.contact'));
@endphp
<div class="content">
	<div class="card">
		<div class="card-body text-center py-5">
			<span class="rounded-circle d-inline-flex p-3 bg-light mb-3"><i class="ti ti-lock fs-24"></i></span>
			<h3 class="h2 mb-3">{{ __('plans.upgrade_title') }}</h3>
			<p class="mb-2">{{ __('plans.upgrade_message', ['feature' => __('plans.features.'.$feature)]) }}</p>
			@if ($needed)
				<p class="mb-3 fw-semibold">{{ __('plans.available_from', ['plan' => __('platform.plans.'.$needed)]) }}</p>
			@endif
			@if ($contact)
				<p class="mb-4 text-muted">
					{{ __('plans.contact_us') }}
					@isset($contact['phone'])<a href="tel:{{ $contact['phone'] }}" class="ms-1">{{ $contact['phone'] }}</a>@endisset
					@isset($contact['email'])<a href="mailto:{{ $contact['email'] }}" class="ms-1">{{ $contact['email'] }}</a>@endisset
				</p>
			@endif
			<a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('app.menu.dashboard') }}</a>
		</div>
	</div>
</div>
@endsection
