@extends('errors.layout', ['code' => '403', 'icon' => 'ti-lock'])

@section('actions')
	<a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.home') }}</a>
@endsection
