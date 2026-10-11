@extends('errors.layout', ['code' => '419', 'icon' => 'ti-clock-off'])

@section('actions')
	<a href="{{ url('/login') }}" class="btn btn-primary">{{ __('errors.sign_in_again') }}</a>
@endsection
