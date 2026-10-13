@extends('layouts.auth')

@section('title', __('auth.register.title').' - Odessa POS')

@section('content')
<div class="main-wrapper">
	<div class="account-content">
		<div class="login-wrapper bg-img">
			<div class="login-content authent-content">
				<form method="POST" action="{{ route('register') }}">
					@csrf
					<div class="login-userset">
						<div class="text-center mb-4">
							<h2 class="fw-bold mb-1">Odessa POS</h2>
							<p class="text-muted mb-0">{{ __('auth.tagline') }}</p>
						</div>
						<div class="login-userheading">
							<h3>{{ __('auth.register.title') }}</h3>
							<h4 class="fs-16">{{ __('auth.register.hint', ['days' => $trialDays]) }}</h4>
						</div>

						@if ($errors->any())
							<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
						@endif

						{{-- Honeypot: hidden from people, filled by bots. --}}
						<div style="position: absolute; left: -9999px;" aria-hidden="true">
							<label for="reg-website">Website</label>
							<input type="text" id="reg-website" name="website" tabindex="-1" autocomplete="off" value="">
						</div>

						<div class="mb-3">
							<label class="form-label" for="reg-shop">{{ __('auth.register.shop_name') }} <span class="text-danger">*</span></label>
							<input type="text" id="reg-shop" name="shop_name" value="{{ old('shop_name') }}" class="form-control" maxlength="120" autofocus required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="reg-owner">{{ __('auth.register.owner_name') }} <span class="text-danger">*</span></label>
							<input type="text" id="reg-owner" name="owner_name" value="{{ old('owner_name') }}" class="form-control" maxlength="120" autocomplete="name" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="reg-email">{{ __('auth.email') }} <span class="text-danger">*</span></label>
							<input type="email" id="reg-email" name="email" value="{{ old('email') }}" class="form-control" autocomplete="username" required>
						</div>
						<div class="mb-3">
							<label class="form-label" for="reg-password">{{ __('auth.password_label') }} <span class="text-danger">*</span></label>
							<input type="password" id="reg-password" name="password" class="form-control" minlength="8" autocomplete="new-password" required>
							<div class="form-text">{{ __('auth.register.password_hint') }}</div>
						</div>
						<div class="mb-3">
							<label class="form-label" for="reg-password2">{{ __('auth.register.password_confirm') }} <span class="text-danger">*</span></label>
							<input type="password" id="reg-password2" name="password_confirmation" class="form-control" minlength="8" autocomplete="new-password" required>
						</div>
						<div class="form-check mb-3">
							<input class="form-check-input" type="checkbox" name="terms" value="1" id="reg-terms" required @checked(old('terms'))>
							<label class="form-check-label" for="reg-terms">
								{!! __('auth.register.accept', ['terms' => '<a href="'.e(route('legal.terms')).'" target="_blank" rel="noopener">'.e(__('legal.terms_link')).'</a>', 'privacy' => '<a href="'.e(route('legal.privacy')).'" target="_blank" rel="noopener">'.e(__('legal.privacy_link')).'</a>']) !!}
							</label>
						</div>
						<div class="form-login">
							<button type="submit" class="btn btn-primary w-100">{{ __('auth.register.submit') }}</button>
						</div>
						<p class="text-muted fs-14 text-center mt-3 mb-0">{{ __('auth.register.have_account') }} <a href="{{ route('login') }}">{{ __('auth.sign_in') }}</a></p>
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
