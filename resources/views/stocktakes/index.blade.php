@extends('layouts.app')

@section('title', __('stocktake.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('stocktake.title') }}</h4>
				<h6>{{ __('stocktake.subtitle') }}</h6>
			</div>
		</div>
		@if ($canManage)
			<div class="page-btn">
				<a href="{{ route('stocktakes.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('stocktake.new') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>{{ __('stocktake.number') }}</th>
							<th>{{ __('stocktake.started') }}</th>
							<th>{{ __('stocktake.warehouse') }}</th>
							<th class="text-end">{{ __('stocktake.progress') }}</th>
							<th>{{ __('stocktake.status') }}</th>
							<th>{{ __('stocktake.by') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($stocktakes as $stocktake)
							<tr data-stocktake="{{ $stocktake->number }}">
								<td><a href="{{ route('stocktakes.show', $stocktake) }}">{{ $stocktake->number }}</a></td>
								<td>{{ $stocktake->started_at->format('Y-m-d H:i') }}</td>
								<td>{{ $stocktake->warehouse?->name }}</td>
								<td class="text-end">{{ $stocktake->counted_count }} / {{ $stocktake->items_count }}</td>
								<td><span class="badge {{ ['open' => 'bg-warning', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'][$stocktake->status] ?? 'bg-secondary' }}">{{ __('stocktake.statuses.'.$stocktake->status) }}</span></td>
								<td>{{ $stocktake->user?->name ?? '—' }}</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">{{ __('stocktake.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $stocktakes])
		</div>
	</div>
</div>
@endsection
