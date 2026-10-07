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
					<span class="text-orange fw-semibold">{{ trans_choice('dashboard.low_stock_alert', $data['low']->count(), ['count' => $data['low']->count()]) }}</span>
					{{ $data['low']->pluck('name')->take(3)->join(', ') }}@if ($data['low']->count() > 3)…@endif
					@can('inventory.view')
						<a href="{{ route('stock.index') }}" class="link-orange text-decoration-underline fw-semibold ms-1">{{ __('dashboard.view_stock') }}</a>
					@endcan
				</div>
				<button type="button" class="btn-close text-gray-9 fs-14" data-bs-dismiss="alert" aria-label="Close"><i class="ti ti-x"></i></button>
			</div>
		@endif

		<div class="row">
			@foreach ([
				['bg-primary', 'text-primary', 'ti ti-file-text', 'dashboard.sales_today', $data['today']['sales_total']],
				['bg-secondary', 'text-secondary', 'ti ti-repeat', 'dashboard.returns_today', $data['today']['returns_total']],
				['bg-teal', 'text-teal', 'ti ti-receipt', 'dashboard.credit_today', $data['today']['credit_given']],
				['bg-info', 'text-info', 'ti ti-wallet', 'dashboard.credit_outstanding', $data['credit']],
			] as [$bg, $text, $icon, $label, $amount])
				<div class="col-xl-3 col-sm-6 col-12 d-flex">
					<div class="card {{ $bg }} sale-widget flex-fill">
						<div class="card-body d-flex align-items-center">
							<span class="sale-icon bg-white {{ $text }}"><i class="{{ $icon }} fs-24"></i></span>
							<div class="ms-2">
								<p class="text-white mb-1">{{ __($label) }}</p>
								<h4 class="text-white">{{ \App\Support\Money::format($amount, $currency) }}</h4>
							</div>
						</div>
					</div>
				</div>
			@endforeach
		</div>

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
