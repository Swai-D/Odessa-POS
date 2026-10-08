{{-- Server-side search box for paginated lists. Keeps the other filters (status, category...) in the URL. --}}
<form method="GET" action="{{ url()->current() }}" class="search-set">
	@foreach (request()->except(['q', 'page']) as $key => $value)
		@if (is_scalar($value))
			<input type="hidden" name="{{ $key }}" value="{{ $value }}">
		@endif
	@endforeach
	<div class="search-input" style="display:block;">
		<input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('app.search') }}" aria-label="{{ __('app.search') }}">
	</div>
</form>
