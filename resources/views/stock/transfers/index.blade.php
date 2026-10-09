@extends('layouts.app')

@section('title', __('transfers.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('transfers.title') }}</h4>
				<h6>{{ __('transfers.subtitle') }}</h6>
			</div>
		</div>
		@if ($canCreate)
			<div class="page-btn">
				<a href="{{ route('stock-transfers.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('transfers.new') }}</a>
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
							<th>@include('partials.sort-header', ['label' => __('transfers.number'), 'key' => 'number'])</th>
							<th>@include('partials.sort-header', ['label' => __('transfers.date'), 'key' => 'date'])</th>
							<th>{{ __('transfers.from') }}</th>
							<th>{{ __('transfers.to') }}</th>
							<th class="text-end">{{ __('transfers.lines') }}</th>
							<th>{{ __('transfers.by') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($transfers as $transfer)
							<tr>
								<td><a href="{{ route('stock-transfers.show', $transfer) }}">{{ $transfer->number }}</a></td>
								<td>{{ $transfer->transferred_at->format('Y-m-d H:i') }}</td>
								<td>{{ $transfer->fromWarehouse?->name }}</td>
								<td>{{ $transfer->toWarehouse?->name }}</td>
								<td class="text-end">{{ $transfer->items_count }}</td>
								<td>{{ $transfer->user?->name ?? '—' }}</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">{{ __('transfers.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $transfers])
		</div>
	</div>
</div>
@endsection
