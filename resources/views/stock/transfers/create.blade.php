@extends('layouts.app')

@section('title', __('transfers.new').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('transfers.new') }}</h4>
				<h6><a href="{{ route('stock-transfers.index') }}">{{ __('transfers.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	@if ($errors->any())
		<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
	@endif

	@if ($warehouses->count() < 2)
		<div class="alert alert-warning">{{ __('transfers.need_two') }}</div>
	@else
		<form method="POST" action="{{ route('stock-transfers.store') }}">
			@csrf
			<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

			<div class="card">
				<div class="card-body">
					<div class="row">
						<div class="col-md-4 mb-3">
							<label class="form-label">{{ __('transfers.from') }}<span class="text-danger ms-1">*</span></label>
							<select name="from_warehouse_id" class="form-select" required>
								@foreach ($warehouses as $id => $name)
									<option value="{{ $id }}" @selected((string) old('from_warehouse_id') === (string) $id)>{{ $name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-4 mb-3">
							<label class="form-label">{{ __('transfers.to') }}<span class="text-danger ms-1">*</span></label>
							<select name="to_warehouse_id" class="form-select" required>
								@foreach ($warehouses as $id => $name)
									<option value="{{ $id }}" @selected((string) old('to_warehouse_id', $warehouses->keys()->get(1)) === (string) $id)>{{ $name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-4 mb-3">
							<label class="form-label">{{ __('transfers.note') }}</label>
							<input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
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
									<th>{{ __('transfers.product') }}</th>
									<th style="width: 160px;">{{ __('transfers.qty') }}</th>
									<th style="width: 40px;"></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<button type="button" class="btn btn-white" id="add-line"><i class="ti ti-circle-plus me-1"></i>{{ __('transfers.add_line') }}</button>
				</div>
			</div>

			<div class="text-end mb-4">
				<button type="submit" class="btn btn-primary">{{ __('transfers.save') }}</button>
			</div>
		</form>
	@endif
</div>
@endsection

@push('extra-js')
<script src="{{ asset('js/product-picker.js') }}"></script>
<script>
	(function () {
		var body = document.querySelector('#lines tbody');
		if (!body) { return; }
		var restoredProducts = @json($restoredProducts);
		var lookupUrl = @json(route('products.lookup'));
		var pickMessage = @json(__('catalog.pick_product'));
		var placeholder = @json(__('catalog.search_product'));
		var oldItems = @json(old('items', []));
		var counter = 0;

		function addLine(item) {
			var i = counter++;
			var row = document.createElement('tr');
			row.innerHTML =
				'<td><div class="js-product-picker" data-url="' + lookupUrl + '" data-tracked="1" data-required="1" data-message="' + pickMessage + '">' +
					'<input type="text" class="form-control" list="products-dl-' + i + '" placeholder="' + placeholder + '" autocomplete="off">' +
					'<datalist id="products-dl-' + i + '"></datalist>' +
					'<input type="hidden" class="line-product" name="items[' + i + '][product_id]"></div></td>' +
				'<td><input type="number" class="form-control line-qty" name="items[' + i + '][quantity]" min="0.001" step="any" required></td>' +
				'<td><a href="javascript:void(0);" class="text-danger line-remove"><i class="ti ti-trash"></i></a></td>';
			body.appendChild(row);

			var picker = row.querySelector('.js-product-picker');
			if (item && item.product_id) {
				row.querySelector('.line-product').value = item.product_id;
				picker.querySelector('input[type="text"]').value = restoredProducts[item.product_id] || '';
				row.querySelector('.line-qty').value = item.quantity || '';
			}
			ProductPicker.attach(picker, function () {});
			row.querySelector('.line-remove').addEventListener('click', function () { row.remove(); });
		}

		document.getElementById('add-line').addEventListener('click', function () { addLine(); });

		var restored = Object.keys(oldItems).map(function (k) { return oldItems[k]; });
		if (restored.length) { restored.forEach(addLine); } else { addLine(); }
	})();
</script>
@endpush
