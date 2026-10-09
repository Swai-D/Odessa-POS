@extends('layouts.app')

@section('title', $transfer->number.' - Odessa POS')

@section('content')
@php($qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $transfer->number }}</h4>
				<h6><a href="{{ route('stock-transfers.index') }}">{{ __('transfers.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-3"><div class="text-muted">{{ __('transfers.date') }}</div>{{ $transfer->transferred_at->format('Y-m-d H:i') }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('transfers.from') }}</div>{{ $transfer->fromWarehouse?->name }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('transfers.to') }}</div>{{ $transfer->toWarehouse?->name }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('transfers.by') }}</div>{{ $transfer->user?->name ?? '—' }}</div>
			</div>
			@if ($transfer->note)<p><span class="text-muted">{{ __('transfers.note') }}:</span> {{ $transfer->note }}</p>@endif

			<table class="table table-sm mb-0">
				<thead>
					<tr><th>{{ __('transfers.product') }}</th><th>{{ __('catalog.fields.sku') }}</th><th class="text-end">{{ __('transfers.qty') }}</th></tr>
				</thead>
				<tbody>
					@foreach ($transfer->items as $item)
						<tr><td>{{ $item->product_name }}</td><td>{{ $item->sku }}</td><td class="text-end">{{ $qty($item->quantity) }}</td></tr>
					@endforeach
				</tbody>
			</table>
		</div>
	</div>
</div>
@endsection
