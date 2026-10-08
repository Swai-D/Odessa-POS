@extends('layouts.app')

@section('title', __('purchases.new').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('purchases.new') }}</h4>
				<h6><a href="{{ route('purchases.index') }}">{{ __('purchases.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('purchases.store') }}" id="purchase-form">
		@csrf
		<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

		<div class="card">
			<div class="card-body">
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.supplier') }}<span class="text-danger ms-1">*</span></label>
						<select name="supplier_id" class="form-select" required>
							<option value=""></option>
							@foreach ($suppliers as $id => $name)
								<option value="{{ $id }}" @selected((string) old('supplier_id') === (string) $id)>{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.warehouse') }}<span class="text-danger ms-1">*</span></label>
						<select name="warehouse_id" class="form-select" required>
							@foreach ($warehouses as $id => $name)
								<option value="{{ $id }}" @selected((string) old('warehouse_id') === (string) $id)>{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.reference') }}</label>
						<input type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}">
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
								<th>{{ __('purchases.product') }}</th>
								<th style="width: 130px;">{{ __('purchases.qty') }}</th>
								<th style="width: 160px;">{{ __('purchases.unit_cost') }}</th>
								<th class="text-end" style="width: 160px;">{{ __('purchases.line_total') }}</th>
								<th style="width: 40px;"></th>
							</tr>
						</thead>
						<tbody></tbody>
						<tfoot>
							<tr>
								<td colspan="3" class="text-end fw-bold">{{ __('purchases.total') }}</td>
								<td class="text-end fw-bold" id="grand-total">0.00</td>
								<td></td>
							</tr>
						</tfoot>
					</table>
				</div>
				<button type="button" class="btn btn-white" id="add-line"><i class="ti ti-circle-plus me-1"></i>{{ __('purchases.add_line') }}</button>
				<div class="form-check mt-3">
					<input type="hidden" name="update_cost" value="0">
					<input class="form-check-input" type="checkbox" id="update_cost" name="update_cost" value="1" @checked(old('update_cost'))>
					<label class="form-check-label" for="update_cost">{{ __('purchases.update_cost') }}</label>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('purchases.pay_now') }}</h5></div>
			<div class="card-body">
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.amount') }}</label>
						<input type="number" step="0.01" min="0" name="payment_amount" class="form-control" value="{{ old('payment_amount') }}">
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.method') }}</label>
						<select name="payment_method" class="form-select">
							<option value=""></option>
							@foreach ($methods as $method)
								<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('pos.methods.'.$method) }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('purchases.payment_reference') }}</label>
						<input type="text" name="payment_reference" class="form-control" maxlength="100" value="{{ old('payment_reference') }}">
					</div>
					<div class="col-12 mb-1">
						<label class="form-label">{{ __('purchases.note') }}</label>
						<input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
					</div>
				</div>
			</div>
		</div>

		<div class="text-end mb-4">
			<button type="submit" class="btn btn-primary">{{ __('purchases.save') }}</button>
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

		function recalc() {
			var total = 0;
			body.querySelectorAll('tr').forEach(function (row) {
				var qty = parseFloat(row.querySelector('.line-qty').value) || 0;
				var cost = parseFloat(row.querySelector('.line-cost').value) || 0;
				var line = Math.round(qty * cost * 100) / 100;
				row.querySelector('.line-total').textContent = format(line);
				total += line;
			});
			document.getElementById('grand-total').textContent = format(total);
		}

		function addLine(item) {
			var i = counter++;
			var row = document.createElement('tr');
			row.innerHTML =
				'<td><div class="js-product-picker" data-url="' + lookupUrl + '" data-required="1" data-message="' + pickMessage + '">' +
					'<input type="text" class="form-control" list="products-dl-' + i + '" placeholder="' + placeholder + '" autocomplete="off">' +
					'<datalist id="products-dl-' + i + '"></datalist>' +
					'<input type="hidden" class="line-product" name="items[' + i + '][product_id]"></div></td>' +
				'<td><input type="number" class="form-control line-qty" name="items[' + i + '][quantity]" min="0.001" step="any" required></td>' +
				'<td><input type="number" class="form-control line-cost" name="items[' + i + '][unit_cost]" min="0" step="0.01" required></td>' +
				'<td class="text-end line-total">0.00</td>' +
				'<td><a href="javascript:void(0);" class="text-danger line-remove"><i class="ti ti-trash"></i></a></td>';
			body.appendChild(row);

			var picker = row.querySelector('.js-product-picker');
			if (item && item.product_id) {
				row.querySelector('.line-product').value = item.product_id;
				picker.querySelector('input[type="text"]').value = restoredProducts[item.product_id] || '';
				row.querySelector('.line-qty').value = item.quantity || '';
				row.querySelector('.line-cost').value = item.unit_cost || '';
			}
			ProductPicker.attach(picker, function (product) {
				row.querySelector('.line-cost').value = product.cost;
				recalc();
			});
			row.querySelectorAll('input').forEach(function (input) { input.addEventListener('input', recalc); });
			row.querySelector('.line-remove').addEventListener('click', function () { row.remove(); recalc(); });
			recalc();
		}

		document.getElementById('add-line').addEventListener('click', function () { addLine(); });

		var restored = Object.keys(oldItems).map(function (k) { return oldItems[k]; });
		if (restored.length) { restored.forEach(addLine); } else { addLine(); }
	})();
</script>
@endpush
