@extends('layouts.app')

@section('title', __('inventory.batches').' - Odessa POS')


@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('inventory.batches') }}</h4>
				<h6>{{ __('inventory.batches_hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('stock.index') }}" class="btn btn-outline-primary"><i class="ti ti-stack-3 me-1"></i>{{ __('inventory.stock_levels') }}</a>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('stock.batches') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
			<div class="d-flex gap-2">
				<a href="{{ route('stock.batches', array_filter(['q' => request('q')])) }}" class="btn btn-sm {{ $expiry === null ? 'btn-primary' : 'btn-white' }}">{{ __('inventory.expiry_filter.all') }}</a>
				<a href="{{ route('stock.batches', array_filter(['q' => request('q'), 'expiry' => 'soon'])) }}" class="btn btn-sm {{ $expiry === 'soon' ? 'btn-warning' : 'btn-white' }}">{{ __('inventory.expiry_filter.soon') }}</a>
				<a href="{{ route('stock.batches', array_filter(['q' => request('q'), 'expiry' => 'expired'])) }}" class="btn btn-sm {{ $expiry === 'expired' ? 'btn-danger' : 'btn-white' }}">{{ __('inventory.expiry_filter.expired') }}</a>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>{{ __('catalog.fields.sku') }}</th>
							<th>{{ __('inventory.fields.product') }}</th>
							<th>{{ __('inventory.fields.warehouse') }}</th>
							<th>{{ __('inventory.batch_fields.received') }}</th>
							<th>{{ __('inventory.batch_fields.source') }}</th>
							<th>{{ __('inventory.batch_fields.batch_number') }}</th>
							<th>{{ __('inventory.batch_fields.expiry') }}</th>
							<th class="text-end">{{ __('inventory.batch_fields.unit_cost') }}</th>
							<th class="text-end">{{ __('inventory.batch_fields.received_qty') }}</th>
							<th class="text-end">{{ __('inventory.batch_fields.remaining_qty') }}</th>
							<th class="text-end">{{ __('inventory.batch_fields.value') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($batches as $batch)
							<tr>
								<td>{{ $batch->product->sku }}</td>
								<td>{{ $batch->product->name }}</td>
								<td>{{ $batch->warehouse->name }}</td>
								<td>{{ $batch->received_at->format('Y-m-d H:i') }}</td>
								<td>{{ __('inventory.batch_sources.'.$batch->source) }}</td>
								<td>{{ $batch->batch_number ?: '—' }}</td>
								<td>
									@if ($batch->expiry_date)
										{{ $batch->expiry_date->format('Y-m-d') }}
										@if ($batch->expiry_date->isPast() && ! $batch->expiry_date->isToday())
											<span class="badge bg-danger ms-1 fs-10">{{ __('inventory.expiry_filter.expired') }}</span>
										@elseif ($batch->expiry_date->lte(now()->addDays(30)))
											<span class="badge bg-warning ms-1 fs-10">{{ __('inventory.expiry_filter.soon') }}</span>
										@endif
									@else
										—
									@endif
								</td>
								<td class="text-end">{{ \App\Support\Money::format($batch->unit_cost) }}</td>
								<td class="text-end">{{ rtrim(rtrim(number_format((float) $batch->quantity_received, 3, '.', ''), '0'), '.') }}</td>
								<td class="text-end">{{ rtrim(rtrim(number_format((float) $batch->quantity_remaining, 3, '.', ''), '0'), '.') }} {{ $batch->product->unit?->short_name }}</td>
								<td class="text-end">{{ \App\Support\Money::format((int) round((float) $batch->quantity_remaining * $batch->unit_cost)) }}</td>
							</tr>
						@empty
							<tr><td colspan="11" class="text-center text-muted py-4">{{ __('inventory.no_batches') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $batches])
		</div>
	</div>
</div>
@endsection
