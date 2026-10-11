{{-- Standalone error page. It must work when the database is down or nobody is signed in, so it never uses the app layout, the menu or the shop. --}}
@extends('layouts.auth')

@section('title', $code.' - Odessa POS')

@section('content')
<div class="main-wrapper">
	<div class="account-content">
		<div class="login-wrapper bg-img">
			<div class="login-content authent-content">
				<div class="login-userset text-center">
					<h2 class="fw-bold mb-3">Odessa POS</h2>
					<span class="rounded-circle d-inline-flex p-3 bg-light mb-3"><i class="ti {{ $icon }} fs-24"></i></span>
					<div class="text-muted fs-14 mb-1">{{ __('errors.error') }} {{ $code }}</div>
					<h3 class="h2 mb-3">{{ __('errors.'.$code.'.title') }}</h3>
					<p class="mb-4">{{ __('errors.'.$code.'.message') }}</p>
					@yield('actions')
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
