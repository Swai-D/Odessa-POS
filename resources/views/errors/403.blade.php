@extends('layouts.app')

@section('title', '403')
@section('content')
<div class="error-box">
	<div class="error-img">
		<img src="{{ asset('assets/img/authentication/error-404.png') }}" class="img-fluid" alt="Img">
	</div>
	<h3 class="h2 mb-3">Access denied</h3>
	<p>You do not have permission to access this page.</p>
	<a href="{{ route('dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
</div>
@endsection