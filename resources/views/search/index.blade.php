@extends('layouts.app')

@section('title', __('search.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('search.title') }}</h4>
				@if ($term !== '')<h6>{{ __('search.results_for', ['term' => $term]) }}</h6>@endif
			</div>
		</div>
	</div>

	<form method="GET" action="{{ route('search') }}" class="card">
		<div class="card-body d-flex gap-2">
			<input type="search" name="q" value="{{ $term }}" class="form-control" placeholder="{{ __('search.placeholder') }}" aria-label="{{ __('app.search') }}" autofocus>
			<button type="submit" class="btn btn-primary">{{ __('app.search') }}</button>
		</div>
	</form>

	@if ($tooShort)
		<p class="text-muted">{{ __('search.too_short') }}</p>
	@elseif ($term !== '' && $groups === [])
		<p class="text-muted">{{ __('search.nothing', ['term' => $term]) }}</p>
	@endif

	@foreach ($groups as $group)
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h5 class="mb-0">{{ __($group['title']) }}</h5>
				<a href="{{ $group['all'] }}" class="btn btn-sm btn-white">{{ __('search.see_all') }}</a>
			</div>
			<div class="card-body p-0">
				<ul class="list-group list-group-flush">
					@foreach ($group['rows'] as $row)
						<li class="list-group-item d-flex justify-content-between">
							<a href="{{ $row['url'] }}">{{ $row['label'] }}</a>
							<span class="text-muted">{{ $row['detail'] }}</span>
						</li>
					@endforeach
				</ul>
			</div>
		</div>
	@endforeach
</div>
@endsection
