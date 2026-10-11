{{-- Server-side search box for paginated lists. Keeps the other filters (status, category...) in the URL.
     Filters the table in place as you type (no page reload), like the template's own tables. --}}
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
				document.querySelectorAll('form[data-live-search]').forEach(function (form) {
					var input = form.querySelector('input[name="q"]');
					var card = form.closest('.card');
					if (!input || !card) return;
					var last = input.value.trim();
					var timer = null;
					var request = null;

					// The direct child of the card that holds the search box stays; everything after it is the table.
					function header() {
						var node = form;
						while (node.parentElement && node.parentElement !== card) node = node.parentElement;
						return node;
					}

					function swap(doc) {
						var fresh = doc.querySelector('form[data-live-search]');
						var freshCard = fresh && fresh.closest('.card');
						if (!freshCard) return false;
						var freshHeader = fresh;
						while (freshHeader.parentElement && freshHeader.parentElement !== freshCard) freshHeader = freshHeader.parentElement;
						var keep = header();
						while (keep.nextSibling) card.removeChild(keep.nextSibling);
						var next = freshHeader.nextSibling;
						while (next) {
							var current = next;
							next = next.nextSibling;
							card.appendChild(document.importNode(current, true));
						}
						return true;
					}

					function search() {
						var value = input.value.trim();
						if (value === last) return;
						last = value;
						var params = new URLSearchParams(new FormData(form));
						params.delete('page');
						if (value === '') params.delete('q');
						var url = form.action.split('?')[0] + (params.toString() ? '?' + params.toString() : '');
						if (request) request.abort();
						request = new AbortController();
						card.style.opacity = '0.6';
						fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: request.signal })
							.then(function (response) {
								if (!response.ok) throw new Error('bad response');
								return response.text();
							})
							.then(function (html) {
								var doc = new DOMParser().parseFromString(html, 'text/html');
								if (!swap(doc)) throw new Error('no table');
								history.replaceState(null, '', url);
							})
							.catch(function (error) {
								if (error.name === 'AbortError') return;
								form.submit(); // fall back to a normal page load
							})
							.finally(function () { card.style.opacity = ''; });
					}

					input.addEventListener('input', function () {
						clearTimeout(timer);
						timer = setTimeout(search, 250);
					});
					// Enter searches straight away without a reload.
					form.addEventListener('submit', function (event) {
						event.preventDefault();
						clearTimeout(timer);
						search();
					});
				});
			})();
		</script>
	@endpush
@endonce
