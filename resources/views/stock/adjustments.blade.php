@extends('layouts.app')

@section('title', __('inventory.stock_adjustments').' - Odessa POS')


@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('inventory.stock_adjustments') }}</h4>
				<h6>{{ __('inventory.stock_adjustments_hint') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('stock-adjustments.index') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
		@if ($canManage)
			<div class="page-btn">
				<a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjustment-modal"><i class="ti ti-circle-plus me-1"></i>{{ __('inventory.new_adjustment') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>{{ __('inventory.fields.date') }}</th>
							<th>{{ __('inventory.fields.product') }}</th>
							<th>{{ __('inventory.fields.warehouse') }}</th>
							<th>{{ __('inventory.fields.type') }}</th>
							<th>{{ __('inventory.fields.quantity') }}</th>
							<th>{{ __('inventory.fields.balance') }}</th>
							<th>{{ __('inventory.fields.reason') }}</th>
							<th>{{ __('inventory.fields.user') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($movements as $movement)
							<tr>
								<td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
								<td>{{ $movement->product?->name }}</td>
								<td>{{ $movement->warehouse?->name }}</td>
								<td>{{ __('inventory.types.'.$movement->type) }}</td>
								<td class="{{ (float) $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">
									{{ ((float) $movement->quantity > 0 ? '+' : '').rtrim(rtrim(number_format((float) $movement->quantity, 3, '.', ''), '0'), '.') }}
								</td>
								<td>{{ rtrim(rtrim(number_format((float) $movement->balance_after, 3, '.', ''), '0'), '.') }}</td>
								<td>{{ $movement->reason }}</td>
								<td>{{ $movement->user?->name ?? '—' }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $movements])
		</div>
	</div>
</div>
@endsection

@push('modals')
@if ($canManage)
<div class="modal fade" id="adjustment-modal">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<div class="page-title"><h4>{{ __('inventory.new_adjustment') }}</h4></div>
				<button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="POST" action="{{ route('stock-adjustments.store') }}">
				@csrf
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">{{ __('inventory.fields.product') }}<span class="text-danger ms-1">*</span></label>
						@include('partials.product-picker', ['name' => 'product_id', 'tracked' => true, 'required' => true, 'selected' => $selectedProduct, 'id' => 'adjustment-product'])
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('inventory.fields.warehouse') }}<span class="text-danger ms-1">*</span></label>
						<select name="warehouse_id" class="form-select" required>
							<option value="">{{ __('catalog.select') }}</option>
							@foreach ($warehouses as $id => $name)
								<option value="{{ $id }}" @selected((string) old('warehouse_id') === (string) $id)>{{ $name }}</option>
							@endforeach
						</select>
					</div>
					<div class="row">
						<div class="col-6 mb-3">
							<label class="form-label">{{ __('inventory.fields.direction') }}<span class="text-danger ms-1">*</span></label>
							<select name="direction" class="form-select" required>
								@foreach (['in', 'out'] as $direction)
									<option value="{{ $direction }}" @selected(old('direction') === $direction)>{{ __('inventory.directions.'.$direction) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-6 mb-3">
							<label class="form-label">{{ __('inventory.fields.quantity') }}<span class="text-danger ms-1">*</span></label>
							<input type="number" step="0.001" min="0.001" name="quantity" class="form-control" value="{{ old('quantity') }}" required>
						</div>
					</div>
					<div class="mb-0">
						<label class="form-label">{{ __('inventory.fields.reason') }}<span class="text-danger ms-1">*</span></label>
						<input type="text" name="reason" class="form-control" value="{{ old('reason') }}" required>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
					<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif
@endpush

@push('extra-js')
<script src="{{ asset('js/product-picker.js') }}"></script>
<script>ProductPicker.attachAll(document);</script>
@endpush
