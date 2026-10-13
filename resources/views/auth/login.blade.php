@extends('layouts.auth')

@section('title', __('auth.sign_in').' - Odessa POS')

@section('content')
@php($shop = app(\App\Support\Tenancy\TenantContext::class)->get())
<div class="main-wrapper">
	<div class="account-content">
		<div class="login-wrapper bg-img">
			<div class="login-content authent-content">
				<form method="POST" action="{{ route('login') }}">
					@csrf
					<div class="login-userset">
						<div class="text-center mb-4">
							<h2 class="fw-bold mb-1">Odessa POS</h2>
							<p class="text-muted mb-0">{{ __('auth.tagline') }}</p>
						</div>
						<div class="login-userheading">
							<h3>{{ __('auth.sign_in') }}</h3>
							<h4 class="fs-16">{{ $shop ? __('auth.sign_in_hint', ['shop' => $shop->name]) : __('auth.sign_in_hint_generic') }}</h4>
						</div>

						@if ($errors->any())
							<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
						@endif

						<div class="mb-3">
							<label class="form-label" for="login-email">{{ __('auth.email') }} <span class="text-danger">*</span></label>
							<div class="input-group">
								<input type="email" id="login-email" name="email" value="{{ old('email') }}" class="form-control border-end-0" autocomplete="username" autofocus required>
								<span class="input-group-text border-start-0"><i class="ti ti-mail"></i></span>
							</div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="login-password">{{ __('auth.password_label') }} <span class="text-danger">*</span></label>
							<div class="pass-group">
								<input type="password" id="login-password" name="password" class="pass-input form-control" autocomplete="current-password" required>
								<span class="ti toggle-password ti-eye-off text-gray-9"></span>
							</div>
						</div>
						<div class="form-login authentication-check">
							<div class="custom-control custom-checkbox">
								<label class="checkboxs ps-4 mb-0 pb-0 line-height-1 fs-16 text-gray-6">
									<input type="checkbox" name="remember" value="1" class="form-control">
									<span class="checkmarks"></span>{{ __('auth.remember_me') }}
								</label>
							</div>
						</div>
						<div class="form-login">
							<button type="submit" class="btn btn-primary w-100">{{ __('auth.sign_in') }}</button>
						</div>
						<p class="text-muted fs-14 text-center mt-3 mb-0" data-forgot-hint>{{ __('auth.forgot_hint') }}</p>
						@if (config('plans.self_signup') && ! $shop)
							<p class="fs-14 text-center mt-2 mb-0"><a href="{{ route('register') }}">{{ __('auth.register.start_trial') }}</a></p>
						@endif

						<div class="my-4 d-flex justify-content-center align-items-center copyright-text">
							<p>&copy; {{ date('Y') }} Odessa POS · <a href="{{ route('legal.terms') }}">{{ __('legal.terms_link') }}</a> · <a href="{{ route('legal.privacy') }}">{{ __('legal.privacy_link') }}</a></p>
						</div>
					</div>
				</form>
				<div class="d-flex justify-content-center align-items-center gap-2 mt-3" aria-label="{{ __('auth.language') }}">
					@foreach (['en' => 'English', 'sw' => 'Kiswahili'] as $code => $name)
						<form method="POST" action="{{ route('locale.switch', $code) }}">
							@csrf
							<button type="submit" class="btn btn-sm {{ app()->getLocale() === $code ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $name }}</button>
						</form>
					@endforeach
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
