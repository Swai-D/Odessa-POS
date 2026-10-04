@extends('layouts.app')

@section('title', __('inventory.stock_levels').' - Odessa POS')

@include('partials.datatable-assets')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('inventory.stock_levels') }}</h4>
				<h6>{{ __('inventory.stock_levels_hint') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('stock.index') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			<div class="search-set">
				<div class="search-input">
					<span class="btn-searchset"><i class="ti ti-search fs-14 feather-search"></i></span>
				</div>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table datatable">
					<thead class="thead-light">
						<tr>
							<th>{{ __('catalog.fields.sku') }}</th>
							<th>{{ __('inventory.fields.product') }}</th>
							<th>{{ __('inventory.fields.warehouse') }}</th>
							<th>{{ __('catalog.fields.unit') }}</th>
							<th>{{ __('catalog.fields.qty') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($stocks as $stock)
							<tr>
								<td>{{ $stock->product->sku }}</td>
								<td>{{ $stock->product->name }}</td>
								<td>{{ $stock->warehouse->name }}</td>
								<td>{{ $stock->product->unit?->short_name ?? '—' }}</td>
								<td>
									{{ rtrim(rtrim(number_format((float) $stock->quantity, 3, '.', ''), '0'), '.') }}
									@if ((float) $stock->product->alert_quantity > 0 && (float) $stock->quantity <= (float) $stock->product->alert_quantity)
										<span class="badge bg-warning ms-1 fs-10">{{ __('catalog.low_stock') }}</span>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
