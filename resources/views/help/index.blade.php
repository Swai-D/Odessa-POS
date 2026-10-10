@extends('layouts.app')

@section('title', __('help.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('help.title') }}</h4>
				<h6>{{ __('help.subtitle') }}</h6>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="GET" action="{{ route('help.index') }}" class="d-flex gap-2">
				<input type="search" name="q" value="{{ $term }}" class="form-control form-control-lg" placeholder="{{ __('help.search_placeholder') }}" aria-label="{{ __('help.search_placeholder') }}" minlength="2" autofocus>
				<button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>{{ __('help.search') }}</button>
			</form>
		</div>
	</div>

	@if ($results !== null)
		<h5 class="mb-3">{{ __('help.results_for', ['term' => $term]) }}</h5>
		@forelse ($results as $hit)
			@php($article = $hit['article'])
			<div class="card mb-2">
				<div class="card-body py-3">
					<a href="{{ route('help.show', [$article->category, $article->slug]) }}" class="fw-bold fs-16">{{ $article->title }}</a>
					<span class="badge bg-light text-dark ms-2">{{ __('help.categories.'.$article->category.'.title') }}</span>
					@if ($article->plan)<span class="badge bg-warning-transparent text-warning ms-1">{{ __('help.plan_badge', ['plan' => ucfirst($article->plan)]) }}</span>@endif
					<p class="mb-0 text-muted">{{ $hit['snippet'] }}</p>
				</div>
			</div>
		@empty
			<div class="card"><div class="card-body text-center text-muted py-5">
				<p class="mb-1">{{ __('help.nothing', ['term' => $term]) }}</p>
				<small>{{ __('help.try_again') }}</small>
			</div></div>
		@endforelse
		<p class="mt-3"><a href="{{ route('help.index') }}">{{ __('help.browse_all') }}</a></p>
	@elseif (mb_strlen($term) === 1)
		<p class="text-muted">{{ __('search.too_short') }}</p>
	@endif

	@if ($results === null)
		<div class="row">
			@foreach ($help->categories() as $category)
				@php($articles = $help->inCategory($category))
				@continue($articles === [])
				<div class="col-xl-4 col-md-6 d-flex">
					<div class="card flex-fill">
						<div class="card-header d-flex align-items-center">
							<span class="avatar avatar-md bg-primary-transparent text-primary me-2"><i class="{{ config('help.categories.'.$category) }} fs-20"></i></span>
							<div>
								<h5 class="mb-0">{{ __('help.categories.'.$category.'.title') }}</h5>
								<small class="text-muted">{{ __('help.categories.'.$category.'.description') }}</small>
							</div>
						</div>
						<div class="card-body">
							<ul class="list-unstyled mb-0">
								@foreach ($articles as $article)
									<li class="py-1">
										<a href="{{ route('help.show', [$category, $article->slug]) }}">{{ $article->title }}</a>
										@if ($article->plan)<span class="badge bg-warning-transparent text-warning ms-1">{{ ucfirst($article->plan) }}</span>@endif
									</li>
								@endforeach
							</ul>
						</div>
					</div>
				</div>
			@endforeach
		</div>
	@endif

	@include('help.partials.contact')
</div>
@endsection
