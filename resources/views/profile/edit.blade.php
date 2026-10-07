@extends('layouts.app')

@section('title', __('profile.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('profile.title') }}</h4>
				<h6>{{ $user->displayRole() }}</h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('profile.details') }}</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('profile.update') }}">
				@csrf
				@method('PUT')
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label" for="name">{{ __('profile.name') }}<span class="text-danger ms-1">*</span></label>
						<input type="text" class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label" for="email">{{ __('profile.email') }}<span class="text-danger ms-1">*</span></label>
						<input type="email" class="form-control" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label" for="locale">{{ __('profile.language') }}</label>
						<select class="form-select" id="locale" name="locale">
							<option value="en" @selected(old('locale', $user->locale) === 'en')>English</option>
							<option value="sw" @selected(old('locale', $user->locale) === 'sw')>Kiswahili</option>
						</select>
					</div>
				</div>
				<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('profile.change_password') }}</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('profile.password') }}">
				@csrf
				@method('PUT')
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label" for="current_password">{{ __('profile.current_password') }}</label>
						<input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label" for="password">{{ __('profile.new_password') }}</label>
						<input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="8" required>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label" for="password_confirmation">{{ __('profile.confirm_password') }}</label>
						<input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="8" required>
					</div>
				</div>
				<button type="submit" class="btn btn-primary">{{ __('profile.change_password') }}</button>
			</form>
		</div>
	</div>
</div>
@endsection
