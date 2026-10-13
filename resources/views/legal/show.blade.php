@extends('layouts.auth')

@section('title', __("legal.$page.title").' - Odessa POS')

@section('content')
@php
	$contactPhone = config('plans.contact.phone');
	$contactEmail = config('plans.contact.email');
@endphp
<div class="main-wrapper">
	<div class="account-content">
		<div class="container py-5" style="max-width: 820px;" data-legal="{{ $page }}">
			<div class="card">
				<div class="card-body p-4 p-md-5">
					<h1 class="mb-1">{{ __("legal.$page.title") }}</h1>
					<p class="text-muted">{{ __('legal.updated', ['date' => $updated]) }}</p>
					<p>{{ __("legal.$page.intro") }}</p>
					@foreach (__("legal.$page.sections") as $section)
						<h5 class="mt-4">{{ $section['h'] }}</h5>
						@foreach ($section['p'] as $paragraph)
							<p class="mb-2">{{ $paragraph }}</p>
						@endforeach
					@endforeach
					@if ($contactPhone || $contactEmail)
						<hr>
						<p class="mb-0">{{ __('legal.contact_line') }}
							@if ($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif
							@if ($contactPhone && $contactEmail) · @endif
							@if ($contactPhone){{ $contactPhone }}@endif
						</p>
					@endif
					<hr>
					<div class="d-flex flex-wrap justify-content-between gap-2">
						<a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('login') }}">&larr; {{ __('legal.back') }}</a>
						<span>
							<a href="{{ route('legal.terms') }}">{{ __('legal.terms_link') }}</a> ·
							<a href="{{ route('legal.privacy') }}">{{ __('legal.privacy_link') }}</a>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
