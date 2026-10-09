@extends('layouts.app')

@section('title', __('till.title').' - Odessa POS')

@section('content')
@php($money = fn (int $amount) => \App\Support\Money::format($amount, $currency))
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('till.title') }}</h4>
				<h6>{{ __('till.subtitle') }}</h6>
			</div>
		</div>
		@if ($canClose)
			<div class="page-btn">
				<a href="{{ route('till-closings.create') }}" class="btn btn-primary"><i class="ti ti-cash me-1"></i>{{ __('till.close') }}</a>
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
							<th>@include('partials.sort-header', ['label' => __('till.closed_at'), 'key' => 'date'])</th>
							@if ($seesAll)<th>{{ __('till.cashier') }}</th>@endif
							<th class="text-end">{{ __('till.expected') }}</th>
							<th class="text-end">{{ __('till.counted') }}</th>
							<th class="text-end">@include('partials.sort-header', ['label' => __('till.difference'), 'key' => 'difference'])</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($closings as $closing)
							<tr>
								<td><a href="{{ route('till-closings.show', $closing) }}">{{ $closing->period_end->format('Y-m-d H:i') }}</a></td>
								@if ($seesAll)<td>{{ $closing->user?->name ?? '—' }}</td>@endif
								<td class="text-end">{{ $money($closing->expected_cash) }}</td>
								<td class="text-end">{{ $money($closing->counted_cash) }}</td>
								<td class="text-end {{ $closing->difference < 0 ? 'text-danger' : ($closing->difference > 0 ? 'text-warning' : 'text-success') }}">{{ $money($closing->difference) }}</td>
							</tr>
						@empty
							<tr><td colspan="{{ $seesAll ? 5 : 4 }}" class="text-center text-muted py-4">{{ __('till.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $closings])
		</div>
	</div>
</div>
@endsection
