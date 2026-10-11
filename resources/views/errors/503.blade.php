@extends('errors.layout', ['code' => '503', 'icon' => 'ti-tool'])

@section('actions')
	<a href="{{ url()->current() }}" class="btn btn-primary">{{ __('errors.try_again') }}</a>
@endsection
