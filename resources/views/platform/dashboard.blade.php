@extends('layouts.app')

@section('title', __('platform.dashboard.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.dashboard.title') }}</h4>
				<h6>{{ __('platform.dashboard.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn d-flex gap-2">
			<a href="{{ route('platform.tenants.index') }}" class="btn btn-white">{{ __('platform.dashboard.view_shops') }}</a>
			<a href="{{ route('platform.tenants.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('platform.new') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<div class="row">
		@foreach ([
			[__('platform.dashboard.shops'), 'ti ti-building-store', $overview['shops'], 'bg-primary'],
			[__('platform.dashboard.active'), 'ti ti-circle-check', $overview['active'], 'bg-success'],
			[__('platform.dashboard.trial'), 'ti ti-hourglass', $overview['trial'], 'bg-warning'],
			[__('platform.dashboard.suspended'), 'ti ti-ban', $overview['suspended'], 'bg-danger'],
		] as [$label, $icon, $value, $color])
			<div class="col-xl-3 col-sm-6 d-flex">
				<div class="card flex-fill">
					<div class="card-body d-flex align-items-center justify-content-between">
						<div>
							<p class="text-muted mb-1">{{ $label }}</p>
							<h3 class="mb-0">{{ number_format($value) }}</h3>
						</div>
						<span class="avatar avatar-md {{ $color }}"><i class="{{ $icon }} fs-20"></i></span>
					</div>
				</div>
			</div>
		@endforeach
	</div>

	<div class="row">
		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header d-flex align-items-center justify-content-between">
					<h5 class="mb-0">{{ __('platform.dashboard.renewals') }}</h5>
					<span class="text-muted fs-13">{{ trans_choice('platform.dashboard.within_days', $overview['warnDays'], ['count' => $overview['warnDays']]) }}</span>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead><tr><th>{{ __('platform.name') }}</th><th>{{ __('platform.paid_until') }}</th><th></th></tr></thead>
							<tbody>
								@forelse ($overview['renewals'] as $shop)
									@php($deadline = $shop->paid_until ?? $shop->trial_ends_at)
									<tr>
										<td><a href="{{ route('platform.tenants.edit', $shop).'#renew' }}">{{ $shop->name }}</a></td>
										<td>{{ $deadline?->format('Y-m-d') ?? '—' }}
											@if ($deadline?->lt(today()))<span class="badge bg-danger ms-1">{{ __('platform.dashboard.overdue') }}</span>@else<span class="badge bg-warning ms-1">{{ __('platform.dashboard.due_soon') }}</span>@endif
										</td>
										<td class="text-end"><a href="{{ route('platform.tenants.edit', $shop).'#renew' }}" class="btn btn-sm btn-white" title="{{ __('platform.renew') }}"><i class="ti ti-cash"></i></a></td>
									</tr>
								@empty
									<tr><td colspan="3" class="text-center text-muted py-4">{{ __('platform.dashboard.no_renewals') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header"><h5 class="mb-0">{{ __('platform.dashboard.revenue') }}</h5></div>
				<div class="card-body">
					<p class="text-muted">{{ __('platform.dashboard.revenue_hint') }}</p>
					@forelse ($overview['revenueByCurrency'] as $revenue)
						<div class="d-flex justify-content-between border-bottom py-2">
							<span>{{ $revenue['currency'] }}</span>
							<strong>{{ \App\Support\Money::format($revenue['amount'], $revenue['currency']) }}</strong>
						</div>
					@empty
						<p class="text-muted mb-0">{{ __('platform.dashboard.no_revenue') }}</p>
					@endforelse
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header"><h5 class="mb-0">{{ __('platform.dashboard.recent_shops') }}</h5></div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead><tr><th>{{ __('platform.name') }}</th><th>{{ __('platform.slug') }}</th><th>{{ __('platform.dashboard.registered') }}</th></tr></thead>
							<tbody>
								@forelse ($overview['recentShops'] as $shop)
									<tr>
										<td><a href="{{ route('platform.tenants.edit', $shop) }}">{{ $shop->name }}</a></td>
										<td>{{ $shop->slug }}</td>
										<td>{{ $shop->created_at?->format('Y-m-d') ?? '—' }}</td>
									</tr>
								@empty
									<tr><td colspan="3" class="text-center text-muted py-4">{{ __('platform.empty') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div class="col-xl-6 d-flex">
			<div class="card flex-fill">
				<div class="card-header d-flex align-items-center justify-content-between gap-2">
					<h5 class="mb-0">{{ __('platform.dashboard.recent_payments') }}</h5>
					<a href="{{ route('platform.payments.index') }}" class="btn btn-light btn-sm">{{ __('platform.payments.title') }}</a>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table mb-0">
							<thead><tr><th>{{ __('platform.name') }}</th><th>{{ __('platform.paid_on') }}</th><th class="text-end">{{ __('platform.amount') }}</th></tr></thead>
							<tbody>
								@forelse ($overview['recentPayments'] as $payment)
									<tr>
										<td>{{ $payment->tenant?->name ?? '—' }}</td>
										<td>{{ $payment->paid_on->format('Y-m-d') }}</td>
										<td class="text-end">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
									</tr>
								@empty
									<tr><td colspan="3" class="text-center text-muted py-4">{{ __('platform.dashboard.no_payments') }}</td></tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection