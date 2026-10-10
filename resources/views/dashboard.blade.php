@extends('layouts.app')

@section('title', __('dashboard.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-2">
		<div class="mb-3">
			<h1 class="mb-1">{{ __('dashboard.welcome', ['name' => auth()->user()?->name]) }}</h1>
			@if ($data)
				<p class="fw-medium">{{ trans_choice('dashboard.today_sales', $data['today']['sales_count'], ['count' => $data['today']['sales_count']]) }}</p>
			@elseif (auth()->user()?->is_super_admin)
				<p class="fw-medium"><a href="{{ route('platform.tenants.index') }}">{{ __('dashboard.go_shops') }}</a></p>
			@endif
		</div>
	</div>

	@if ($data)
		@if ($data['low']->isNotEmpty())
			<div class="alert bg-orange-transparent alert-dismissible fade show mb-4">
				<div>
					<i class="ti ti-info-circle fs-14 text-orange me-2"></i>
					<span class="text-orange fw-semibold">{{ trans_choice('dashboard.low_stock_alert', $data['low_count'], ['count' => $data['low_count']]) }}</span>
					{{ $data['low']->pluck('name')->take(3)->join(', ') }}@if ($data['low']->count() > 3)…@endif
					@can('inventory.view')
						<a href="{{ route('stock.index') }}" class="link-orange text-decoration-underline fw-semibold ms-1">{{ __('dashboard.view_stock') }}</a>
					@endcan
				</div>
				<button type="button" class="btn-close text-gray-9 fs-14" data-bs-dismiss="alert" aria-label="Close"><i class="ti ti-x"></i></button>
			</div>
		@endif

		<div class="row">
			@php
				$metrics = [
					...($data['net_profit'] !== null ? [['bg-success', 'text-success', 'ti ti-chart-infographic', 'dashboard.net_profit', $data['net_profit'], 'money']] : []),
					['bg-teal', 'text-teal', 'ti ti-chart-line', 'dashboard.gross_profit', $data['gross_profit'], 'money'],
					['bg-primary', 'text-primary', 'ti ti-file-text', 'dashboard.sales_today', $data['today']['sales_total'], 'money'],
					['bg-info', 'text-info', 'ti ti-chart-bar', 'dashboard.sales_month', $data['sales_month'], 'money'],
					['bg-orange', 'text-orange', 'ti ti-alert-triangle', 'dashboard.low_stock_count', $data['low_count'], 'count'],
					['bg-secondary', 'text-secondary', 'ti ti-package', 'dashboard.products_count', $data['products_count'], 'count'],
					['bg-danger', 'text-danger', 'ti ti-shopping-cart', 'dashboard.cogs', $data['cogs'], 'money'],
					...($data['expenses'] !== null ? [['bg-dark', 'text-dark', 'ti ti-receipt-2', 'dashboard.expenses_month', $data['expenses'], 'money']] : []),
				];
				$chartConfig = [
					'currency' => $currency,
					'locale' => app()->getLocale() === 'sw' ? 'sw-TZ' : 'en-TZ',
					'labels' => ['sales' => __('dashboard.sales_trend'), 'no_data' => __('reports.no_data')],
				];
			@endphp
			@foreach ($metrics as [$bg, $text, $icon, $label, $amount, $format])
				<div class="col-xl-3 col-sm-6 col-12 d-flex">
					<div class="card {{ $bg }} sale-widget flex-fill">
						<div class="card-body d-flex align-items-center">
							<span class="sale-icon bg-white {{ $text }}"><i class="{{ $icon }} fs-24"></i></span>
							<div class="ms-2">
								<p class="text-white mb-1">{{ __($label) }}</p>
								<h4 class="text-white">{{ $format === 'money' ? \App\Support\Money::format($amount, $currency) : number_format($amount) }}</h4>
							</div>
						</div>
					</div>
				</div>
			@endforeach
		</div>

		<div class="row">
			<div class="col-12 d-flex">
				<div class="card flex-fill">
					<div class="card-header d-flex align-items-center justify-content-between">
						<h5 class="mb-0">{{ __('dashboard.sales_trend') }}</h5>
						<span class="text-muted fs-13">{{ __('dashboard.last_7_days') }}</span>
					</div>
					<div class="card-body">
						<div id="dashboard-sales-chart" role="img" aria-label="{{ __('dashboard.sales_trend') }}" style="min-height:320px"></div>
					</div>
				</div>
			</div>
		</div>
		<script type="application/json" id="analytics-chart-config">@json($chartConfig)</script>
		<script type="application/json" id="dashboard-chart-data">@json($data['days'])</script>

		<div class="row">
			<div class="col-xl-6 d-flex">
				<div class="card flex-fill">
					<div class="card-header"><h5 class="mb-0">{{ __('dashboard.last_7_days') }}</h5></div>
					<div class="card-body p-0">
						@php($peak = max(1, max(array_column($data['days'], 'total'))))
						<div class="table-responsive">
							<table class="table mb-0">
								<thead class="thead-light">
									<tr><th>{{ __('pos.sales.date') }}</th><th>{{ __('dashboard.sales_count') }}</th><th>{{ __('pos.sales.total') }}</th><th style="width:35%"></th></tr>
								</thead>
								<tbody>
									@foreach (array_reverse($data['days']) as $day)
										<tr>
											<td>{{ $day['date'] }}</td>
											<td>{{ $day['count'] }}</td>
											<td>{{ \App\Support\Money::format($day['total'], $currency) }}</td>
											<td><div class="progress" style="height:6px"><div class="progress-bar" style="width: {{ round($day['total'] / $peak * 100) }}%"></div></div></td>
										</tr>
									@endforeach
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-xl-6 d-flex">
				<div class="card flex-fill">
					<div class="card-header d-flex align-items-center justify-content-between">
						<h5 class="mb-0">{{ __('dashboard.recent_sales') }}</h5>
						<a href="{{ route('sales.index') }}" class="fs-13 fw-medium text-decoration-underline">{{ __('dashboard.view_all') }}</a>
					</div>
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table mb-0">
								<thead class="thead-light">
									<tr><th>{{ __('pos.sales.number') }}</th><th>{{ __('pos.sales.customer') }}</th><th>{{ __('pos.sales.total') }}</th><th>{{ __('pos.sales.status') }}</th></tr>
								</thead>
								<tbody>
									@forelse ($data['recent'] as $sale)
										<tr>
											<td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
											<td>{{ $sale->customer?->name ?? __('dashboard.walk_in') }}</td>
											<td>{{ \App\Support\Money::format($sale->total, $sale->currency) }}</td>
											<td>
												<span class="badge table-badge {{ ['paid' => 'bg-success', 'partial' => 'bg-warning', 'unpaid' => 'bg-danger'][$sale->payment_status] ?? 'bg-secondary' }} fw-medium fs-10">
													{{ __('pos.sales.statuses.'.$sale->payment_status) }}
												</span>
											</td>
										</tr>
									@empty
										<tr><td colspan="4" class="text-center text-muted py-4">{{ __('dashboard.no_sales') }}</td></tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	@endif
</div>
@endsection

@push('extra-js')
	<script src="{{ asset('js/analytics-charts.js') }}?v={{ filemtime(public_path('js/analytics-charts.js')) }}"></script>
@endpush
