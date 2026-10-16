@extends('layouts.app')

@section('title', ($promotion->exists ? __('promotions.edit') : __('promotions.new')).' - Odessa POS')

@php
	use App\Support\Money;
	$isEdit = $promotion->exists;
	$type = old('type', $promotion->type);
	$kind = old('value_kind', $promotion->value_kind ?? 'percent');
	$scope = old('scope', $promotion->scope);
	$fixedLike = $type === 'fixed' || ($type === 'bulk' && $kind === 'fixed');
	$valueText = old('value', $isEdit && $type !== 'bogo'
		? ($fixedLike ? Money::toMajor($promotion->value) : rtrim(rtrim(number_format($promotion->value / 100, 2, '.', ''), '0'), '.'))
		: '');
	$chosenDays = old('days', $promotion->days ? explode(',', $promotion->days) : []);
	$chosenCategories = old('scope_ids', $scope === 'categories' ? $selectedIds : []);
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $isEdit ? __('promotions.edit') : __('promotions.new') }}</h4>
				<h6><a href="{{ route('promotions.index') }}">{{ __('promotions.back') }}</a></h6>
			</div>
		</div>
	</div>

	@if ($errors->any())
		<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
	@endif

	<form method="POST" action="{{ $isEdit ? route('promotions.update', $promotion) : route('promotions.store') }}" id="promotion-form">
		@csrf
		@if ($isEdit) @method('PUT') @endif

		<div class="card"><div class="card-body">
			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label" for="p-name">{{ __('promotions.name') }} <span class="text-danger">*</span></label>
					<input type="text" id="p-name" name="name" class="form-control" maxlength="120" value="{{ old('name', $promotion->name) }}" required>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="p-type">{{ __('promotions.type') }}</label>
					<select id="p-type" name="type" class="form-select">
						@foreach (['percent', 'fixed', 'bogo', 'bulk'] as $t)
							<option value="{{ $t }}" @selected($type === $t)>{{ __('promotions.types.'.$t) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3 d-flex align-items-end">
					<div class="form-check form-switch">
						<input class="form-check-input" type="checkbox" role="switch" id="p-active" name="is_active" value="1" @checked(old('is_active', $promotion->is_active))>
						<label class="form-check-label" for="p-active">{{ __('promotions.active') }}</label>
					</div>
				</div>
			</div>
			<p class="text-muted small mt-2 mb-0" id="p-type-hint"></p>
		</div></div>

		<div class="card"><div class="card-header"><h5 class="mb-0">{{ __('promotions.offer') }}</h5></div><div class="card-body">
			<div class="row g-3">
				<div class="col-md-3" data-for="bulk">
					<label class="form-label" for="p-min">{{ __('promotions.min_qty') }}</label>
					<input type="number" step="any" min="0" id="p-min" name="min_qty" class="form-control" value="{{ old('min_qty', $promotion->min_qty) }}">
				</div>
				<div class="col-md-3" data-for="bulk">
					<label class="form-label" for="p-kind">{{ __('promotions.value_kind') }}</label>
					<select id="p-kind" name="value_kind" class="form-select">
						<option value="percent" @selected($kind === 'percent')>{{ __('promotions.kind_percent') }}</option>
						<option value="fixed" @selected($kind === 'fixed')>{{ __('promotions.kind_fixed') }}</option>
					</select>
				</div>
				<div class="col-md-3" data-for="percent fixed bulk">
					<label class="form-label" for="p-value" id="p-value-label">{{ __('promotions.value') }}</label>
					<input type="number" step="any" min="0" id="p-value" name="value" class="form-control" value="{{ $valueText }}">
				</div>
				<div class="col-md-3" data-for="bogo">
					<label class="form-label" for="p-buy">{{ __('promotions.buy_qty') }}</label>
					<input type="number" min="1" id="p-buy" name="buy_qty" class="form-control" value="{{ old('buy_qty', $promotion->buy_qty) }}">
				</div>
				<div class="col-md-3" data-for="bogo">
					<label class="form-label" for="p-get">{{ __('promotions.get_qty') }}</label>
					<input type="number" min="1" id="p-get" name="get_qty" class="form-control" value="{{ old('get_qty', $promotion->get_qty) }}">
				</div>
			</div>
		</div></div>

		<div class="card"><div class="card-header"><h5 class="mb-0">{{ __('promotions.applies_to') }}</h5></div><div class="card-body">
			<div class="row g-3">
				<div class="col-md-4">
					<select name="scope" id="p-scope" class="form-select">
						<option value="all" @selected($scope === 'all')>{{ __('promotions.scope_all') }}</option>
						<option value="categories" @selected($scope === 'categories')>{{ __('promotions.scope_categories') }}</option>
						<option value="products" @selected($scope === 'products')>{{ __('promotions.scope_products') }}</option>
					</select>
				</div>
				<div class="col-12" data-scope="categories">
					<div class="row g-2">
						@foreach ($categories as $id => $name)
							<div class="col-md-3 col-6"><label class="form-check"><input type="checkbox" class="form-check-input" name="scope_ids[]" value="{{ $id }}" @checked(in_array($id, array_map('intval', (array) $chosenCategories), true))> <span class="form-check-label">{{ $name }}</span></label></div>
						@endforeach
					</div>
				</div>
				<div class="col-12" data-scope="products">
					<input type="text" id="p-product-search" class="form-control" list="p-product-list" placeholder="{{ __('catalog.search_product') }}" autocomplete="off" data-url="{{ route('products.lookup') }}">
					<datalist id="p-product-list"></datalist>
					<div class="d-flex flex-wrap gap-2 mt-2" id="p-product-chips">
						@foreach ($selectedProducts as $product)
							<span class="badge bg-light text-dark border" data-chip>{{ $product->name }} ({{ $product->sku }})<input type="hidden" name="scope_ids[]" value="{{ $product->id }}"><a href="javascript:void(0);" class="ms-2 text-danger" data-chip-remove>&times;</a></span>
						@endforeach
					</div>
				</div>
			</div>
		</div></div>

		<div class="card"><div class="card-header"><h5 class="mb-0">{{ __('promotions.when') }}</h5></div><div class="card-body">
			<div class="row g-3">
				<div class="col-md-3"><label class="form-label" for="p-start">{{ __('promotions.starts_on') }}</label><input type="date" id="p-start" name="starts_on" class="form-control" value="{{ old('starts_on', $promotion->starts_on?->format('Y-m-d')) }}"></div>
				<div class="col-md-3"><label class="form-label" for="p-end">{{ __('promotions.ends_on') }}</label><input type="date" id="p-end" name="ends_on" class="form-control" value="{{ old('ends_on', $promotion->ends_on?->format('Y-m-d')) }}"></div>
				<div class="col-md-3"><label class="form-label" for="p-from">{{ __('promotions.time_from') }}</label><input type="time" id="p-from" name="time_from" class="form-control" value="{{ old('time_from', $promotion->time_from) }}"></div>
				<div class="col-md-3"><label class="form-label" for="p-to">{{ __('promotions.time_to') }}</label><input type="time" id="p-to" name="time_to" class="form-control" value="{{ old('time_to', $promotion->time_to) }}"></div>
				<div class="col-12">
					<label class="form-label d-block">{{ __('promotions.days') }}</label>
					@foreach ([1, 2, 3, 4, 5, 6, 7] as $day)
						<label class="form-check form-check-inline"><input type="checkbox" class="form-check-input" name="days[]" value="{{ $day }}" @checked(in_array((string) $day, array_map('strval', (array) $chosenDays), true))> <span class="form-check-label">{{ __('promotions.weekdays.'.$day) }}</span></label>
					@endforeach
					<div class="form-text">{{ __('promotions.when_hint') }}</div>
				</div>
			</div>
		</div></div>

		<div class="card"><div class="card-header"><h5 class="mb-0">{{ __('promotions.coupon') }}</h5></div><div class="card-body">
			<div class="row g-3">
				<div class="col-md-4"><label class="form-label" for="p-code">{{ __('promotions.code') }}</label><input type="text" id="p-code" name="code" class="form-control text-uppercase" maxlength="40" value="{{ old('code', $promotion->code) }}" autocomplete="off"><div class="form-text">{{ __('promotions.code_hint') }}</div></div>
				<div class="col-md-4"><label class="form-label" for="p-max">{{ __('promotions.max_uses') }}</label><input type="number" min="1" id="p-max" name="max_uses" class="form-control" value="{{ old('max_uses', $promotion->max_uses) }}"><div class="form-text">{{ __('promotions.max_uses_hint') }}</div></div>
			</div>
		</div></div>

		<div class="d-flex gap-2 mb-4">
			<button type="submit" class="btn btn-primary">{{ __('promotions.save') }}</button>
			<a href="{{ route('promotions.index') }}" class="btn btn-secondary">{{ __('app.cancel') }}</a>
		</div>
	</form>
</div>
@endsection

@push('extra-js')
<script>
(function () {
	var hints = @json(__('promotions.type_hints'));
	var labels = { percent: @json(__('promotions.value_percent')), fixed: @json(__('promotions.value_fixed')) };
	var type = document.getElementById('p-type');
	var kind = document.getElementById('p-kind');
	var scope = document.getElementById('p-scope');

	function sync() {
		var t = type.value;
		document.querySelectorAll('[data-for]').forEach(function (el) {
			el.style.display = el.getAttribute('data-for').split(' ').indexOf(t) >= 0 ? '' : 'none';
		});
		var fixed = t === 'fixed' || (t === 'bulk' && kind.value === 'fixed');
		document.getElementById('p-value-label').textContent = fixed ? labels.fixed : labels.percent;
		document.getElementById('p-type-hint').textContent = hints[t] || '';
		document.querySelectorAll('[data-scope]').forEach(function (el) {
			var on = el.getAttribute('data-scope') === scope.value;
			el.style.display = on ? '' : 'none';
			el.querySelectorAll('input[name="scope_ids[]"]').forEach(function (input) { input.disabled = !on; });
		});
	}
	type.addEventListener('change', sync);
	kind.addEventListener('change', sync);
	scope.addEventListener('change', sync);
	sync();

	// Product search: pick from the list to add a chip.
	var search = document.getElementById('p-product-search');
	var list = document.getElementById('p-product-list');
	var chips = document.getElementById('p-product-chips');
	var found = {};
	var timer = null;
	search.addEventListener('input', function () {
		var label = search.value;
		if (found[label]) {
			if (!chips.querySelector('input[value="' + found[label] + '"]')) {
				var chip = document.createElement('span');
				chip.className = 'badge bg-light text-dark border';
				chip.setAttribute('data-chip', '');
				chip.appendChild(document.createTextNode(label));
				var hidden = document.createElement('input');
				hidden.type = 'hidden'; hidden.name = 'scope_ids[]'; hidden.value = found[label];
				chip.appendChild(hidden);
				var remove = document.createElement('a');
				remove.href = 'javascript:void(0);'; remove.className = 'ms-2 text-danger'; remove.setAttribute('data-chip-remove', ''); remove.innerHTML = '&times;';
				chip.appendChild(remove);
				chips.appendChild(chip);
			}
			search.value = '';
			return;
		}
		window.clearTimeout(timer);
		timer = window.setTimeout(function () {
			if (search.value.trim().length < 2) { return; }
			fetch(search.getAttribute('data-url') + '?q=' + encodeURIComponent(search.value.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
				.then(function (r) { return r.json(); }).then(function (data) {
					list.innerHTML = '';
					(data.data || []).forEach(function (p) {
						found[p.label] = p.id;
						var option = document.createElement('option');
						option.value = p.label;
						list.appendChild(option);
					});
				}).catch(function () {});
		}, 200);
	});
	chips.addEventListener('click', function (event) {
		var remove = event.target.closest('[data-chip-remove]');
		if (remove) { remove.closest('[data-chip]').remove(); }
	});
})();
</script>
@endpush
