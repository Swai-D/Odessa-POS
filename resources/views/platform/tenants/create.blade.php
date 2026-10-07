@extends('layouts.app')

@section('title', __('platform.new').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.new') }}</h4>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('platform.tenants.store') }}">
		@csrf
		
		@include('platform.tenants._form')
	</form>
</div>
@endsection
