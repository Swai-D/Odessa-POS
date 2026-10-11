@extends('layouts.app')

@section('title', __('branches.title').' - Odessa POS')

@section('content')
@php
	$money = fn (int $amount) => \App\Support\Money::format($amount, $currency);
	$qty = fn (float $value) => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
@endphp
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('branches.title') }}</h4>
				<h6>{{ __('branches.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('reports.branches.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-secondary"><i class="ti ti-download me-1"></i>{{ __('branches.export') }}</a>
		</div>
	</div>

	<form method="GET" action="{{ route('reports.branches') }}" class="card">
		<div class="card-body d-flex flex-wrap align-items-end gap-3">
			<div>
				<label class="form-label">{{ __('branches.from') }}</label>
				<input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label">{{ __('branches.to') }}</label>
				<input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
			</div>
			<button type="submit" class="btn btn-primary">{{ __('branches.apply') }}</button>
		</div>
	</form>

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr>
							<th>{{ __('branches.branch') }}</th>
							<th class="text-end">{{ __('branches.sales_count') }}</th>
							<th class="text-end">{{ __('branches.gross') }}</th>
							<th class="text-end">{{ __('branches.returns') }}</th>
							<th class="text-end">{{ __('branches.net') }}</th>
							<th class="text-end">{{ __('branches.average') }}</th>
							<th class="text-end">{{ __('branches.gross_profit') }}</th>
							<th class="text-end">{{ __('branches.purchases') }}</th>
							<th class="text-end">{{ __('branches.stock_value') }}</th>
							<th style="min-width:140px">{{ __('branches.share') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($rows as $row)
							<tr data-branch="{{ $row['name'] }}">
								<td>{{ $row['name'] }} <span class="text-muted">{{ $row['code'] }}</span>@unless ($row['active']) <span class="badge badge-soft-secondary">{{ __('branches.inactive') }}</span>@endunless</td>
								<td class="text-end">{{ number_format($row['sales_count']) }}</td>
								<td class="text-end">{{ $money($row['gross']) }}</td>
								<td class="text-end">{{ $money($row['returns']) }}</td>
								<td class="text-end fw-semibold">{{ $money($row['net']) }}</td>
								<td class="text-end">{{ $money($row['average']) }}</td>
								<td class="text-end">{{ $money($row['gross_profit']) }}</td>
								<td class="text-end">{{ $money($row['purchases']) }}</td>
								<td class="text-end">{{ $money($row['stock_value']) }} <span class="text-muted fs-12">({{ $qty($row['stock_units']) }})</span></td>
								<td>
									<div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $row['share'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $row['share'] }}%"></div></div>
									<span class="fs-12 text-muted">{{ $row['share'] }}%</span>
								</td>
							</tr>
						@empty
							<tr><td colspan="10" class="text-center text-muted py-4">{{ __('branches.none') }}</td></tr>
						@endforelse
					</tbody>
					@if (count($rows) > 1)
						<tfoot>
							<tr class="fw-semibold">
								<td>{{ __('branches.total') }}</td>
								<td class="text-end">{{ number_format($totals['sales_count']) }}</td>
								<td class="text-end">{{ $money($totals['gross']) }}</td>
								<td class="text-end">{{ $money($totals['returns']) }}</td>
								<td class="text-end">{{ $money($totals['net']) }}</td>
								<td></td>
								<td class="text-end">{{ $money($totals['gross_profit']) }}</td>
								<td class="text-end">{{ $money($totals['purchases']) }}</td>
								<td class="text-end">{{ $money($totals['stock_value']) }}</td>
								<td></td>
							</tr>
						</tfoot>
					@endif
				</table>
			</div>
		</div>
		<div class="card-footer text-muted fs-13">{{ __('branches.note') }}</div>
	</div>
</div>
@endsection
