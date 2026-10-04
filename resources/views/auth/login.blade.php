@extends('layouts.auth')

@section('title', __('auth.sign_in'))

@section('content')
<main class="login-page">
    <h1>{{ __('auth.sign_in') }}</h1>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <label for="email">{{ __('auth.email') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email')<p role="alert">{{ $message }}</p>@enderror
        <label for="password">{{ __('auth.password_label') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<p role="alert">{{ $message }}</p>@enderror
        <label><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit">{{ __('auth.sign_in') }}</button>
    </form>
</main>
@endsection