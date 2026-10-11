@extends('errors.layout', ['code' => '404', 'icon' => 'ti-map-off'])

@section('actions')
	<a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.home') }}</a>
@endsection
