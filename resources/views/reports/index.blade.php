@extends('layouts.app')

@section('title', __('reports.title').' - Odessa POS')

@section('content')
@php
	$exportQuery = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
	$export = fn (string $report) => route('reports.export', ['report' => $report] + $exportQuery);
	$money = fn (int $amount) => \App\Support\Money::format($amount, $currency);
	$qty = fn (float $value) => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
	$chartConfig = [
		'currency' => $currency,
		'locale' => app()->getLocale() === 'sw' ? 'sw-TZ' : 'en-TZ',
		'labels' => [
			'sales' => __('reports.sales_amount'), 'orders' => __('reports.transactions'), 'payments' => __('reports.payments_by_method'),
			'revenue' => __('reports.revenue'), 'cost' => __('reports.cost_of_goods'), 'gross_profit' => __('reports.gross_profit'),
			'expenses' => __('reports.expenses'), 'net_profit' => __('reports.net_profit'), 'no_data' => __('reports.no_data'),
		],
	];
	$chartMethods = array_map(fn (array $row): array => ['label' => __('pos.methods.'.$row['method']), 'amount' => $row['amount']], $methods);
	$chartProfit = $profitLoss === null ? null : array_intersect_key($profitLoss, array_flip(['revenue', 'cost', 'gross_profit', 'expenses', 'net_profit']));
	$chartData = ['days' => array_reverse($days), 'methods' => $chartMethods, 'profit' => $chartProfit];
@endphp
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('reports.title') }}</h4>
				<h6>{{ __('reports.hint') }}</h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="GET" action="{{ route('reports.index') }}" class="card">
		<div class="card-body d-flex flex-wrap align-items-end gap-3">
			<div>
				<label class="form-label">{{ __('reports.from') }}</label>
				<input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label">{{ __('reports.to') }}</label>
				<input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
			</div>
			<button type="submit" class="btn btn-primary">{{ __('reports.apply') }}</button>
		</div>
	</form>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation"><button class="nav-link active" id="report-summary-tab" data-bs-toggle="tab" data-bs-target="#report-summary" type="button" role="tab" aria-controls="report-summary" aria-selected="true">{{ __('reports.tab_summary') }}</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="report-products-tab" data-bs-toggle="tab" data-bs-target="#report-products" type="button" role="tab" aria-controls="report-products" aria-selected="false">{{ __('reports.tab_products') }}</button></li>
		<li class="nav-item" role="presentation"><button class="nav-link" id="report-customers-tab" data-bs-toggle="tab" data-bs-target="#report-customers" type="button" role="tab" aria-controls="report-customers" aria-selected="false">{{ __('reports.tab_customers_stock') }}</button></li>
		@if ($profitLoss)
			<li class="nav-item" role="presentation"><button class="nav-link" id="report-profit-tab" data-bs-toggle="tab" data-bs-target="#report-profit" type="button" role="tab" aria-controls="report-profit" aria-selected="false">{{ __('reports.tab_profit') }}</button></li>
		@endif
		@if ($purchases)
			<li class="nav-item" role="presentation"><button class="nav-link" id="report-purchases-tab" data-bs-toggle="tab" data-bs-target="#report-purchases" type="button" role="tab" aria-controls="report-purchases" aria-selected="false">{{ __('reports.tab_purchases') }}</button></li>
		@endif
		<li class="nav-item" role="presentation"><button class="nav-link" id="report-daily-tab" data-bs-toggle="tab" data-bs-target="#report-daily" type="button" role="tab" aria-controls="report-daily" aria-selected="false">{{ __('reports.tab_daily') }}</button></li>
	</ul>
	<div class="tab-content">
	<div class="tab-pane fade show active" id="report-summary" role="tabpanel" aria-labelledby="report-summary-tab" tabindex="0">
	<div class="row">
		@foreach ([
			['bg-primary', 'text-primary', 'ti ti-file-text', 'reports.net_sales', $summary['net'], trans_choice('reports.sales_count', $summary['count'], ['count' => $summary['count']])],
			['bg-secondary', 'text-secondary', 'ti ti-repeat', 'reports.returns', $summary['returns'], null],
			['bg-teal', 'text-teal', 'ti ti-receipt', 'reports.credit_given', $summary['credit'], null],
			['bg-info', 'text-info', 'ti ti-wallet', 'reports.paid', $summary['paid'], null],
		] as [$bg, $text, $icon, $label, $amount, $sub])
			<div class="col-xl-3 col-sm-6 col-12 d-flex">
				<div class="card {{ $bg }} sale-widget flex-fill">
					<div class="card-body d-flex align-items-center">
						<span class="sale-icon bg-white {{ $text }}"><i class="{{ $icon }} fs-24"></i></span>
						<div class="ms-2">
							<p class="text-white mb-1">{{ __($label) }}</p>
							<h4 class="text-white mb-0">{{ $money($amount) }}</h4>
							@if ($sub)<small class="text-white">{{ $sub }}</small>@endif
						</div>
					</div>
				</div>
			</div>
		@endforeach
	</div>
	<p class="text-muted fs-13">{{ __('reports.summary_note', ['gross' => $money($summary['gross']), 'discounts' => $money($summary['discounts']), 'tax' => $money($summary['tax'])]) }}</p>
	<div class="row">
		<div class="col-xl-8 d-flex">
			<div class="card flex-fill">
				<div class="card-header"><h5 class="mb-0">{{ __('reports.sales_chart') }}</h5></div>
				<div class="card-body"><div id="report-sales-chart" role="img" aria-label="{{ __('reports.sales_chart') }}" style="min-height:320px"></div></div>
			</div>
		</div>
		<div class="col-xl-4 d-flex">
			<div class="card flex-fill">
				<div class="card-header"><h5 class="mb-0">{{ __('reports.payment_mix') }}</h5></div>
				<div class="card-body">
					@if (count($methods))
						<div id="report-payments-chart" role="img" aria-label="{{ __('reports.payment_mix') }}" style="min-height:320px"></div>
					@else
						<p class="text-muted text-center py-5 mb-0">{{ __('reports.no_data') }}</p>
					@endif
				</div>
			</div>
		</div>
	</div>
	</div>

	<div class="tab-pane fade" id="report-products" role="tabpanel" aria-labelledby="report-products-tab" tabindex="0">
	<div class="row">
		<div class="col-xl-7 d-flex">
			<div class="card flex-fill">
				<div class="card-header d-flex justify-content-between align-items-start"><div><h5 class="mb-0">{{ __('reports.top_products') }}</h5><small class="text-muted">{{ __('reports.top_products_hint') }}</small></div><a href="{{ $export('products') }}" class="btn btn-sm btn-white"><i class="ti ti-download me-1"></i>CSV</a></div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead class="thead-light"><tr><th>{{ __('reports.product') }}</th><th class="text-end">{{ __('reports.quantity') }}</th><th class="text-end">{{ __('reports.revenue') }}</th><th class="text-end">{{ __('reports.profit') }}</th></tr></thead>
							<tbody>
								@forelse ($top as $row)
									<tr><td>{{ $row['name'] }}</td><td class="text-end">{{ $qty($row['quantity']) }}</td><td class="text-end">{{ $money($row['revenue']) }}</td><td class="text-end">{{ $money($row['profit']) }}</td></tr>
								@empty
									<tr><td colspan="4" class="text-center text-muted py-4">{{ __('reports.no_data') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="col-xl-5 d-flex">
			<div class="card flex-fill">
				<div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">{{ __('reports.payments_by_method') }}</h5><a href="{{ $export('payments') }}" class="btn btn-sm btn-white"><i class="ti ti-download me-1"></i>CSV</a></div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<tbody>
								@forelse ($methods as $row)
									<tr><td>{{ __('pos.methods.'.$row['method']) }}</td><td class="text-end">{{ $money($row['amount']) }}</td></tr>
								@empty
									<tr><td class="text-center text-muted py-4">{{ __('reports.no_data') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
	</div>

	<div class="tab-pane fade" id="report-customers" role="tabpanel" aria-labelledby="report-customers-tab" tabindex="0">
	<div class="row">
		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header d-flex justify-content-between align-items-start"><div><h5 class="mb-0">{{ __('reports.customer_balances') }}</h5><small class="text-muted">{{ __('reports.as_of_now') }}</small></div><a href="{{ $export('balances') }}" class="btn btn-sm btn-white"><i class="ti ti-download me-1"></i>CSV</a></div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<tbody>
								@forelse ($balances as $row)
									<tr><td>{{ $row['name'] }}</td><td class="text-end">{{ $money($row['balance']) }}</td></tr>
								@empty
									<tr><td class="text-center text-muted py-4">{{ __('reports.nobody_owes') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header"><h5 class="mb-0">{{ __('reports.stock') }}</h5><small class="text-muted">{{ __('reports.as_of_now') }}</small></div>
				<div class="card-body">
					<div class="d-flex justify-content-between mb-2"><span>{{ __('reports.stock_value') }}</span><strong>{{ $money($stock['value']) }}</strong></div>
					<div class="d-flex justify-content-between mb-3"><span>{{ __('reports.stock_units') }}</span><strong>{{ $qty($stock['units']) }} ({{ trans_choice('reports.products_count', $stock['products'], ['count' => $stock['products']]) }})</strong></div>
					<div class="d-flex justify-content-between align-items-center"><h6 class="mb-0">{{ __('reports.low_stock') }}</h6><a href="{{ $export('stock') }}" class="btn btn-sm btn-white"><i class="ti ti-download me-1"></i>CSV</a></div>
					@forelse ($low as $product)
						<div class="d-flex justify-content-between border-top py-1"><span>{{ $product->name }}</span><span>{{ $qty((float) ($product->getAttribute('on_hand') ?? 0)) }} / {{ $qty((float) $product->alert_quantity) }}</span></div>
					@empty
						<p class="text-muted mb-0">{{ __('reports.no_low_stock') }}</p>
					@endforelse
				</div>
			</div>
		</div>
	</div>
	</div>

	@if ($profitLoss)
		<div class="tab-pane fade" id="report-profit" role="tabpanel" aria-labelledby="report-profit-tab" tabindex="0">
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('reports.profit_loss') }}</h5><small class="text-muted">{{ __('reports.profit_loss_hint') }}</small></div>
			<div class="card-body">
				<div id="report-profit-chart" role="img" aria-label="{{ __('reports.profit_loss') }}" style="min-height:300px"></div>
				<div class="row g-3 mb-3">
					<div class="col-md-6"><div class="d-flex justify-content-between mb-2"><span>{{ __('reports.revenue') }}</span><strong>{{ $money($profitLoss['revenue']) }}</strong></div><div class="d-flex justify-content-between"><span>{{ __('reports.cost_of_goods') }}</span><strong>{{ $money($profitLoss['cost']) }}</strong></div></div>
					<div class="col-md-6"><div class="d-flex justify-content-between mb-2"><span>{{ __('reports.gross_profit') }}</span><strong>{{ $money($profitLoss['gross_profit']) }}</strong></div><div class="d-flex justify-content-between"><span>{{ __('reports.expenses') }}</span><strong>{{ $money($profitLoss['expenses']) }}</strong></div></div>
				</div>
				@foreach ($profitLoss['by_category'] as $row)
					<div class="d-flex justify-content-between text-muted ps-3 fs-13"><span>{{ $row['name'] }}</span><span>{{ $money($row['amount']) }}</span></div>
				@endforeach
				<div class="d-flex justify-content-between border-top pt-2 mt-3 fs-16"><span class="fw-bold">{{ __('reports.net_profit') }}</span><strong class="{{ $profitLoss['net_profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($profitLoss['net_profit']) }}</strong></div>
			</div>
		</div>
		</div>
	@endif

	@if ($purchases)
		<div class="tab-pane fade" id="report-purchases" role="tabpanel" aria-labelledby="report-purchases-tab" tabindex="0">
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('reports.purchases') }}</h5></div>
			<div class="card-body d-flex flex-wrap gap-5">
				<div><p class="mb-1 text-muted">{{ __('reports.purchases_total') }}</p><h5>{{ $money($purchases['total']) }}</h5><small>{{ trans_choice('reports.purchases_count', $purchases['count'], ['count' => $purchases['count']]) }}</small></div>
				<div><p class="mb-1 text-muted">{{ __('reports.payable') }}</p><h5>{{ $money($purchases['payable']) }}</h5><small>{{ __('reports.as_of_now') }}</small></div>
			</div>
		</div>
		</div>
	@endif

	<div class="tab-pane fade" id="report-daily" role="tabpanel" aria-labelledby="report-daily-tab" tabindex="0">
	<div class="card">
		<div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">{{ __('reports.by_day') }}</h5><a href="{{ $export('daily') }}" class="btn btn-sm btn-white"><i class="ti ti-download me-1"></i>CSV</a></div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light"><tr><th>{{ __('pos.sales.date') }}</th><th class="text-end">{{ __('reports.sales') }}</th><th class="text-end">{{ __('pos.sales.total') }}</th></tr></thead>
					<tbody>
						@foreach ($days as $day)
							<tr><td>{{ $day['date'] }}</td><td class="text-end">{{ $day['count'] }}</td><td class="text-end">{{ $money($day['total']) }}</td></tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
	</div>
	</div>
	<script type="application/json" id="analytics-chart-config">@json($chartConfig)</script>
	<script type="application/json" id="reports-chart-data">@json($chartData)</script>
</div>
@endsection

@push('extra-js')
	<script src="{{ asset('js/analytics-charts.js') }}?v={{ filemtime(public_path('js/analytics-charts.js')) }}"></script>
@endpush
