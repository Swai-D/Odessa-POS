@extends('errors.layout', ['code' => '500', 'icon' => 'ti-alert-triangle'])

@section('actions')
	<a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.home') }}</a>
@endsection
