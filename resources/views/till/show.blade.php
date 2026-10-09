@extends('layouts.app')

@section('title', __('till.title').' - Odessa POS')

@section('content')
@php($money = fn (int $amount) => \App\Support\Money::format($amount, $closing->currency))
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('till.report') }} {{ $closing->period_end->format('Y-m-d H:i') }}</h4>
				<h6><a href="{{ route('till-closings.index') }}">{{ __('till.title') }}</a></h6>
			</div>
		</div>
		<div class="page-btn">
			<button type="button" class="btn btn-white" onclick="window.print()"><i class="ti ti-printer me-1"></i>{{ __('till.print') }}</button>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-4"><div class="text-muted">{{ __('till.cashier') }}</div>{{ $closing->user?->name ?? '—' }}</div>
				<div class="col-md-4"><div class="text-muted">{{ __('till.period_label') }}</div>{{ $closing->period_start->format('Y-m-d H:i') }} → {{ $closing->period_end->format('Y-m-d H:i') }}</div>
				<div class="col-md-4"><div class="text-muted">{{ __('till.sales') }}</div>{{ $closing->sales_count }} · {{ $money($closing->sales_total) }}</div>
			</div>

			<table class="table table-sm w-auto mb-4">
				<tbody>
					<tr><td>{{ __('till.opening_float') }}</td><td class="text-end">{{ $money($closing->opening_float) }}</td></tr>
					<tr><td>{{ __('till.cash_sales') }}</td><td class="text-end">{{ $money($closing->cash_sales) }}</td></tr>
					<tr><td>{{ __('till.cash_refunds') }}</td><td class="text-end">- {{ $money($closing->cash_refunds) }}</td></tr>
					<tr class="border-top"><th>{{ __('till.expected') }}</th><th class="text-end">{{ $money($closing->expected_cash) }}</th></tr>
					<tr><td>{{ __('till.counted') }}</td><td class="text-end">{{ $money($closing->counted_cash) }}</td></tr>
					<tr><th>{{ __('till.difference') }}</th><th class="text-end {{ $closing->difference < 0 ? 'text-danger' : ($closing->difference > 0 ? 'text-warning' : 'text-success') }}">{{ $money($closing->difference) }}</th></tr>
					<tr><td>{{ __('till.float_kept') }}</td><td class="text-end">{{ $money($closing->float_kept) }}</td></tr>
					<tr><td>{{ __('till.to_bank') }}</td><td class="text-end">{{ $money($closing->counted_cash - $closing->float_kept) }}</td></tr>
				</tbody>
			</table>

			@if ($closing->by_method)
				<h6>{{ __('till.by_method') }}</h6>
				<table class="table table-sm w-auto mb-3">
					<tbody>
						@foreach ($closing->by_method as $method => $amount)
							<tr><td>{{ __('pos.methods.'.$method) }}</td><td class="text-end">{{ $money((int) $amount) }}</td></tr>
						@endforeach
					</tbody>
				</table>
			@endif

			@if ($closing->note)<p><span class="text-muted">{{ __('till.note') }}:</span> {{ $closing->note }}</p>@endif
		</div>
	</div>
</div>
@endsection
