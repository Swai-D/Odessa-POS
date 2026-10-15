@extends('layouts.app')

@section('title', __('quotations.new').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('quotations.new') }}</h4>
				<h6><a href="{{ route('quotations.index') }}">{{ __('quotations.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('quotations.store') }}">
		@csrf
		<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

		<div class="card">
			<div class="card-body">
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('quotations.customer') }}</label>
						<select name="customer_id" class="form-select">
							<option value="">{{ __('quotations.not_a_customer') }}</option>
							@foreach ($customers as $id => $name)
								<option value="{{ $id }}" @selected((string) old('customer_id') === (string) $id)>{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('quotations.customer_name') }}</label>
						<input type="text" name="customer_name" class="form-control" maxlength="150" value="{{ old('customer_name') }}">
						<div class="form-text">{{ __('quotations.customer_name_hint') }}</div>
					</div>
					<div class="col-md-2 mb-3">
						<label class="form-label">{{ __('quotations.valid_until') }}</label>
						<input type="date" name="valid_until" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('valid_until', now()->addDays(14)->toDateString()) }}">
					</div>
					<div class="col-md-2 mb-3">
						<label class="form-label">{{ __('quotations.discount_percent') }}</label>
						<input type="number" name="discount_percent" class="form-control" min="0" max="100" step="0.01" value="{{ old('discount_percent') }}">
					</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table align-middle" id="lines">
						<thead>
							<tr>
								<th>{{ __('quotations.product') }}</th>
								<th style="width: 130px;">{{ __('quotations.qty') }}</th>
								<th class="text-end" style="width: 160px;">{{ __('quotations.unit_price') }}</th>
								<th class="text-end" style="width: 160px;">{{ __('quotations.line_total') }}</th>
								<th style="width: 40px;"></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<div class="text-muted small mb-2">{{ __('quotations.price_hint') }}</div>
				<button type="button" class="btn btn-white" id="add-line"><i class="ti ti-circle-plus me-1"></i>{{ __('quotations.add_line') }}</button>
				<div class="mt-3">
					<label class="form-label">{{ __('quotations.note') }}</label>
					<input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
				</div>
			</div>
		</div>

		<div class="text-end mb-4">
			<button type="submit" class="btn btn-primary">{{ __('quotations.save') }}</button>
		</div>
	</form>
</div>
@endsection

@push('extra-js')
<script src="{{ asset('js/product-picker.js') }}"></script>
<script>
	(function () {
		var restoredProducts = @json($restoredProducts);
		var lookupUrl = @json(route('products.lookup'));
		var pickMessage = @json(__('catalog.pick_product'));
		var placeholder = @json(__('catalog.search_product'));
		var oldItems = @json(old('items', []));
		var body = document.querySelector('#lines tbody');
		var counter = 0;

		function format(n) { return (Math.round(n * 100) / 100).toFixed(2); }

		function addLine(item) {
			var i = counter++;
			var row = document.createElement('tr');
			row.innerHTML =
				'<td><div class="js-product-picker" data-url="' + lookupUrl + '" data-required="1" data-message="' + pickMessage + '">' +
					'<input type="text" class="form-control" list="products-dl-' + i + '" placeholder="' + placeholder + '" autocomplete="off">' +
					'<datalist id="products-dl-' + i + '"></datalist>' +
					'<input type="hidden" class="line-product" name="items[' + i + '][product_id]"></div></td>' +
				'<td><input type="number" class="form-control line-qty" name="items[' + i + '][quantity]" min="0.001" step="any" required></td>' +
				'<td class="text-end line-price" data-price="0">0.00</td>' +
				'<td class="text-end line-total">0.00</td>' +
				'<td><a href="javascript:void(0);" class="text-danger line-remove"><i class="ti ti-trash"></i></a></td>';
			body.appendChild(row);

			function recalc() {
				var price = parseFloat(row.querySelector('.line-price').dataset.price) || 0;
				var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
				row.querySelector('.line-total').textContent = format(price * qty);
			}

			var picker = row.querySelector('.js-product-picker');
			if (item && item.product_id) {
				row.querySelector('.line-product').value = item.product_id;
				picker.querySelector('input[type="text"]').value = restoredProducts[item.product_id] || '';
				row.querySelector('.line-qty').value = item.quantity || '';
			}
			ProductPicker.attach(picker, function (product) {
				var cell = row.querySelector('.line-price');
				cell.dataset.price = product.price;
				cell.textContent = format(parseFloat(product.price) || 0);
				recalc();
			});
			row.querySelector('.line-qty').addEventListener('input', recalc);
			row.querySelector('.line-remove').addEventListener('click', function () { row.remove(); });
		}

		document.getElementById('add-line').addEventListener('click', function () { addLine(); });

		var restored = Object.keys(oldItems).map(function (k) { return oldItems[k]; });
		if (restored.length) { restored.forEach(addLine); } else { addLine(); }
	})();
</script>
@endpush
