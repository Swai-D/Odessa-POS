@extends('errors.layout', ['code' => '429', 'icon' => 'ti-hourglass'])

@section('actions')
	<a href="{{ url('/') }}" class="btn btn-primary">{{ __('errors.home') }}</a>
@endsection
