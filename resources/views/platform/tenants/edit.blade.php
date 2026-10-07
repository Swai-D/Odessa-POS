@extends('layouts.app')

@section('title', __('platform.edit').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.edit') }}</h4>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('platform.tenants.update', $tenant) }}">
		@csrf
		@method('PUT')
		@include('platform.tenants._form')
	</form>
</div>
@endsection
