@extends('layouts.app')

@section('title', $article->title.' - '.__('help.title').' - Odessa POS')

@push('extra-css')
<style>
	.help-body h2 { font-size: 1.25rem; margin: 1.75rem 0 .75rem; scroll-margin-top: 90px; }
	.help-body h3 { font-size: 1.05rem; margin: 1.25rem 0 .5rem; }
	.help-body ol, .help-body ul { padding-left: 1.4rem; }
	.help-body li { margin-bottom: .35rem; }
	.help-body blockquote { border-left: 4px solid #ff9f43; background: rgba(255, 159, 67, .08); padding: .6rem 1rem; margin: 1rem 0; border-radius: 0 6px 6px 0; }
	.help-body blockquote p { margin: 0; }
	.help-body table { width: 100%; margin: 1rem 0; }
	.help-body th, .help-body td { border: 1px solid rgba(128, 128, 128, .25); padding: .4rem .6rem; }
	.help-body code { background: rgba(128, 128, 128, .15); padding: .1rem .35rem; border-radius: 4px; }
</style>
@endpush

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $article->title }}</h4>
				<h6>
					<a href="{{ route('help.index') }}">{{ __('help.title') }}</a> /
					{{ __('help.categories.'.$article->category.'.title') }}
				</h6>
			</div>
		</div>
		<div class="page-btn">
			<form method="GET" action="{{ route('help.index') }}" class="d-flex gap-2">
				<input type="search" name="q" class="form-control" placeholder="{{ __('help.search_placeholder') }}" aria-label="{{ __('help.search_placeholder') }}" minlength="2">
			</form>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-3 order-lg-2 mb-3">
			@if ($rendered['toc'] !== [])
				<div class="card">
					<div class="card-header"><h6 class="mb-0">{{ __('help.on_this_page') }}</h6></div>
					<div class="card-body">
						<ul class="list-unstyled mb-0">
							@foreach ($rendered['toc'] as $item)
								<li class="py-1"><a href="#{{ $item['id'] }}">{{ $item['title'] }}</a></li>
							@endforeach
						</ul>
					</div>
				</div>
			@endif
			<div class="card">
				<div class="card-header"><h6 class="mb-0">{{ __('help.categories.'.$article->category.'.title') }}</h6></div>
				<div class="card-body">
					<ul class="list-unstyled mb-0">
						@foreach ($siblings as $other)
							<li class="py-1">
								@if ($other->slug === $article->slug)
									<strong>{{ $other->title }}</strong>
								@else
									<a href="{{ route('help.show', [$other->category, $other->slug]) }}">{{ $other->title }}</a>
								@endif
							</li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>

		<div class="col-lg-9 order-lg-1">
			<div class="card">
				<div class="card-body">
					@if ($article->plan)
						<p><span class="badge bg-warning-transparent text-warning">{{ __('help.plan_badge', ['plan' => ucfirst($article->plan)]) }}</span></p>
					@endif
					<p class="text-muted fs-16">{{ $article->summary }}</p>
					<div class="help-body">{!! $rendered['html'] !!}</div>
				</div>
			</div>

			<div class="d-flex justify-content-between">
				@if ($previous)
					<a href="{{ route('help.show', [$previous->category, $previous->slug]) }}" class="btn btn-white"><i class="ti ti-arrow-left me-1"></i>{{ $previous->title }}</a>
				@else <span></span> @endif
				@if ($next)
					<a href="{{ route('help.show', [$next->category, $next->slug]) }}" class="btn btn-white">{{ $next->title }}<i class="ti ti-arrow-right ms-1"></i></a>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection
