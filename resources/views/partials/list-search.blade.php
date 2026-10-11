{{-- Server-side search box for paginated lists. Keeps the other filters (status, category...) in the URL.
     Searches as you type (debounced) like the template's own tables; Enter still works. --}}
<form method="GET" action="{{ url()->current() }}" class="search-set" data-live-search>
	@foreach (request()->except(['q', 'page']) as $key => $value)
		@if (is_scalar($value))
			<input type="hidden" name="{{ $key }}" value="{{ $value }}">
		@endif
	@endforeach
	<div class="search-input" style="display:block;">
		<input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('app.search') }}" aria-label="{{ __('app.search') }}" autocomplete="off">
	</div>
</form>
@once
	@push('extra-js')
		<script>
			(function () {
				var KEY = 'odessa-live-search-focus';
				document.querySelectorAll('form[data-live-search]').forEach(function (form) {
					var input = form.querySelector('input[name="q"]');
					if (!input) return;
					var last = input.value;
					var timer = null;

					// After an automatic reload, put the cursor back at the end of the box so typing can continue.
					try {
						if (sessionStorage.getItem(KEY) === location.pathname) {
							sessionStorage.removeItem(KEY);
							input.focus();
							input.setSelectionRange(input.value.length, input.value.length);
						}
					} catch (e) {}

					input.addEventListener('input', function () {
						clearTimeout(timer);
						timer = setTimeout(function () {
							var value = input.value.trim();
							if (value === last.trim()) return;
							last = input.value;
							try { sessionStorage.setItem(KEY, location.pathname); } catch (e) {}
							form.submit();
						}, 400);
					});
					// Enter submits straight away.
					form.addEventListener('submit', function () { clearTimeout(timer); });
				});
			})();
		</script>
	@endpush
@endonce
