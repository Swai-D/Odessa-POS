@extends('layouts.app')

@section('title', __('statement.title').' - Odessa POS')

@push('extra-css')
	<style>
		@media print {
			.header, .sidebar, .sidebar-overlay, .copyright-footer, .no-print, .alert { display: none !important; }
			.page-wrapper { margin: 0 !important; padding: 0 !important; }
			.card { border: 0 !important; box-shadow: none !important; }
		}
	</style>
@endpush

@section('content')
@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $currency);
@endphp
<div class="content" data-statement>
	<div class="page-header no-print">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('statement.title') }}</h4>
				<h6>{{ $customer->name }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a href="javascript:window.print();" title="{{ __('statement.print') }}"><i class="ti ti-printer"></i></a></li>
			<li><a href="{{ route('customers.index') }}" title="{{ __('statement.back') }}"><i class="ti ti-arrow-left"></i></a></li>
		</ul>
	</div>

	<form method="GET" action="{{ route('customers.statement', $customer) }}" class="card no-print">
		<div class="card-body d-flex flex-wrap align-items-end gap-3">
			<div>
				<label class="form-label" for="st-from">{{ __('statement.from') }}</label>
				<input id="st-from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
			</div>
			<div>
				<label class="form-label" for="st-to">{{ __('statement.to') }}</label>
				<input id="st-to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
			</div>
			<div class="form-check mb-2">
				<input id="st-open" type="checkbox" name="open" value="1" class="form-check-input" @checked($filters['open'])>
				<label class="form-check-label" for="st-open">{{ __('statement.open_only') }}</label>
			</div>
			<button type="submit" class="btn btn-primary">{{ __('statement.apply') }}</button>
		</div>
	</form>

	<div class="card">
		<div class="card-body">
			<div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
				<div>
					<h4 class="mb-1">{{ $shopName }}</h4>
					<div class="text-muted">{{ __('statement.title') }} · {{ now()->format('Y-m-d') }}</div>
				</div>
				<div class="text-md-end">
					<div class="fw-semibold">{{ $customer->name }}</div>
					@if ($customer->phone)<div>{{ $customer->phone }}</div>@endif
					@if ($customer->address)<div>{{ $customer->address }}</div>@endif
					@if ($filters['from'] || $filters['to'])
						<div class="text-muted">{{ $filters['from'] ?: '…' }} → {{ $filters['to'] ?: '…' }}</div>
					@endif
				</div>
			</div>

			@if ($statement['sales']->isEmpty())
				<p class="text-muted mb-0" data-empty>{{ __('statement.empty') }}</p>
			@else
				<div class="table-responsive">
					<table class="table">
						<thead class="thead-light">
							<tr>
								<th>{{ __('statement.date') }}</th>
								<th>{{ __('statement.number') }}</th>
								<th class="text-end">{{ __('statement.total') }}</th>
								<th class="text-end">{{ __('statement.returned') }}</th>
								<th class="text-end">{{ __('statement.paid') }}</th>
								<th class="text-end">{{ __('statement.balance') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach ($statement['sales'] as $sale)
								<tr data-sale="{{ $sale->number }}">
									<td>{{ $sale->sold_at?->format('Y-m-d') }}</td>
									<td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
									<td class="text-end">{{ $fmt($sale->total) }}</td>
									<td class="text-end">{{ $sale->returned_total > 0 ? $fmt($sale->returned_total) : '—' }}</td>
									<td class="text-end">{{ $fmt($sale->amount_paid) }}</td>
									<td class="text-end {{ $sale->balance_due > 0 ? 'text-danger fw-semibold' : '' }}">{{ $fmt($sale->balance_due) }}</td>
								</tr>
							@endforeach
						</tbody>
						<tfoot>
							<tr class="fw-bold">
								<td colspan="2">{{ __('statement.totals') }}</td>
								<td class="text-end">{{ $fmt($statement['total']) }}</td>
								<td class="text-end">{{ $fmt($statement['returned']) }}</td>
								<td class="text-end">{{ $fmt($statement['paid']) }}</td>
								<td class="text-end" data-balance>{{ $fmt($statement['balance']) }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
				<div class="alert alert-light border mt-3 mb-0 d-flex justify-content-between">
					<span class="fw-semibold">{{ __('statement.amount_due') }}</span>
					<span class="fw-bold fs-18">{{ $fmt($statement['balance']) }}</span>
				</div>
			@endif
		</div>
	</div>
</div>
@endsection
