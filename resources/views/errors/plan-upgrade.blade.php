@extends('layouts.app')

@section('title', __('plans.upgrade_title'))
@section('content')
<div class="content">
	<div class="card">
		<div class="card-body text-center py-5">
			<h3 class="h2 mb-3">{{ __('plans.upgrade_title') }}</h3>
			<p class="mb-4">{{ __('plans.upgrade_message', ['feature' => __('plans.features.'.$feature)]) }}</p>
			<a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('app.menu.dashboard') }}</a>
		</div>
	</div>
</div>
@endsection
