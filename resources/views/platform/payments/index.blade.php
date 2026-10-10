@extends('layouts.app')

@section('title', __('platform.payments.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.payments.title') }}</h4>
				<h6>{{ __('platform.payments.hint') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('platform.payments.export') }}" href="{{ route('platform.payments.export', array_filter($filters)) }}"><i class="ti ti-download"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('platform.payments.reset') }}" href="{{ route('platform.payments.index') }}"><i class="ti ti-refresh"></i></a></li>
		</ul>
	</div>

	<form method="GET" action="{{ route('platform.payments.index') }}" class="card">
		<div class="card-body d-flex flex-wrap align-items-end gap-3">
			<div>
				<label class="form-label" for="payment-from">{{ __('platform.payments.from') }}</label>
				<input id="payment-from" type="date" name="from" class="form-control" value="{{ $filters['from'] }}" max="{{ today()->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label" for="payment-to">{{ __('platform.payments.to') }}</label>
				<input id="payment-to" type="date" name="to" class="form-control" value="{{ $filters['to'] }}" max="{{ today()->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label" for="payment-shop">{{ __('platform.payments.shop') }}</label>
				<select id="payment-shop" name="tenant_id" class="form-select">
					<option value="">{{ __('platform.payments.all_shops') }}</option>
					@foreach ($shops as $shop)
						<option value="{{ $shop->getKey() }}" @selected($filters['tenant_id'] === $shop->getKey())>{{ $shop->name }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label class="form-label" for="payment-currency">{{ __('platform.payments.currency') }}</label>
				<select id="payment-currency" name="currency" class="form-select">
					<option value="">{{ __('platform.payments.all_currencies') }}</option>
					@foreach ($currencies as $currency)
						<option value="{{ $currency }}" @selected($filters['currency'] === $currency)>{{ $currency }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label class="form-label" for="payment-method">{{ __('platform.method') }}</label>
				<select id="payment-method" name="method" class="form-select">
					<option value="">{{ __('platform.payments.all_methods') }}</option>
					@foreach ($methods as $method)
						<option value="{{ $method }}" @selected($filters['method'] === $method)>{{ __('pos.methods.'.$method) }}</option>
					@endforeach
				</select>
			</div>
			<button type="submit" class="btn btn-primary"><i class="ti ti-filter me-1"></i>{{ __('platform.payments.apply') }}</button>
		</div>
	</form>

	<div class="row">
		<div class="col-12">
			<div class="card">
				<div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
					<h5 class="mb-0">{{ __('platform.payments.totals') }}</h5>
					<span class="text-muted">{{ $payments->total() }} {{ __('platform.payments.transactions') }}</span>
				</div>
				<div class="card-body d-flex flex-wrap gap-4">
					@forelse ($totals as $total)
						<div><span class="text-muted me-2">{{ $total['currency'] }}</span><strong>{{ \App\Support\Money::format($total['amount'], $total['currency']) }}</strong></div>
					@empty
						<span class="text-muted">{{ __('platform.payments.none') }}</span>
					@endforelse
				</div>
			</div>
		</div>
	</div>

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead>
						<tr>
							<th>{{ __('platform.name') }}</th>
							<th>{{ __('platform.paid_on') }}</th>
							<th>{{ __('platform.payments.plan') }}</th>
							<th>{{ __('platform.method') }}</th>
							<th>{{ __('platform.reference') }}</th>
							<th>{{ __('platform.payments.period') }}</th>
							<th class="text-end">{{ __('platform.amount') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($payments as $payment)
							<tr>
								<td><a href="{{ route('platform.tenants.edit', $payment->tenant) }}">{{ $payment->tenant?->name }}</a></td>
								<td>{{ $payment->paid_on->format('Y-m-d') }}</td>
								<td>{{ $payment->plan_name ?? ($payment->plan ? __('platform.plans.'.$payment->plan) : '—') }}</td>
								<td>{{ __('pos.methods.'.$payment->method) }}</td>
								<td>{{ $payment->reference ?? '—' }}</td>
								<td>{{ $payment->period_start->format('Y-m-d') }} – {{ $payment->period_end->format('Y-m-d') }}</td>
								   <td class="text-end">
									   <div>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</div>
									   @if ($payment->discount_amount > 0)
										   <small class="text-muted">{{ __('platform.discount') }}: {{ \App\Support\Money::format($payment->discount_amount, $payment->currency) }} · {{ $payment->discount_reason }}</small>
									   @endif
								   </td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center text-muted py-4">{{ __('platform.payments.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $payments])
		</div>
	</div>
</div>
@endsection