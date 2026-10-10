@extends('layouts.app')

@section('title', __('platform.edit').' - Odessa POS')

@section('content')
@php
	$monthlyPrice = $billingPlan?->monthly_price;
	$annualPrice = $billingPlan?->annual_price;
	$defaultMonths = old('months', $monthlyPrice !== null ? 1 : 12);
@endphp
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.edit') }}</h4>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<div class="card" id="renew">
		<div class="card-header">
			<h5 class="mb-0">{{ __('platform.renew') }}</h5>
			<small class="text-muted">{{ __('platform.renew_hint', ['date' => $tenant->paid_until?->format('Y-m-d') ?? '—']) }}</small>
			@if ($billingPlan === null || $monthlyPrice === null || $annualPrice === null)
				<div class="alert alert-warning mt-2 mb-0">{{ __('platform.plan_prices_incomplete') }} <a href="{{ route('platform.plans.index') }}">{{ __('platform.plans_title') }}</a></div>
			@endif
		</div>
		<div class="card-body">
			<form method="POST" action="{{ route('platform.tenants.renewals.store', $tenant) }}" class="row g-3">
				@csrf
				<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
				<div class="col-md-3">
					<label class="form-label" for="renewal-months">{{ __('platform.billing_period') }}</label>
					<select id="renewal-months" name="months" class="form-select" required>
						<option value="1" data-price="{{ $monthlyPrice ?? '' }}" @selected((int) $defaultMonths === 1) @disabled($monthlyPrice === null)>{{ __('platform.monthly') }}{{ $monthlyPrice === null ? ' - '.__('platform.price_not_configured') : ' - '.\App\Support\Money::format($monthlyPrice, $currency) }}</option>
						<option value="12" data-price="{{ $annualPrice ?? '' }}" @selected((int) $defaultMonths === 12) @disabled($annualPrice === null)>{{ __('platform.annual') }}{{ $annualPrice === null ? ' - '.__('platform.price_not_configured') : ' - '.\App\Support\Money::format($annualPrice, $currency) }}</option>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="renewal-discount">{{ __('platform.discount') }} ({{ $currency }})</label>
					<input id="renewal-discount" type="number" name="discount_amount" min="0" step="0.01" class="form-control" value="{{ old('discount_amount', '0.00') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label" for="renewal-discount-reason">{{ __('platform.discount_reason') }}</label>
					<input id="renewal-discount-reason" type="text" name="discount_reason" maxlength="250" class="form-control" value="{{ old('discount_reason') }}">
				</div>
				<div class="col-md-3">
					<label class="form-label" for="renewal-method">{{ __('platform.method') }}</label>
					<select id="renewal-method" name="method" class="form-select">
						@foreach ($methods as $method)
							<option value="{{ $method }}" @selected(old('method', 'mobile_money') === $method)>{{ __('pos.methods.'.$method) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="renewal-paid-on">{{ __('platform.paid_on') }}</label>
					<input id="renewal-paid-on" type="date" name="paid_on" class="form-control" max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_on', now()->format('Y-m-d')) }}" required>
				</div>
				<div class="col-md-3">
					<label class="form-label" for="renewal-reference">{{ __('platform.reference') }}</label>
					<input id="renewal-reference" type="text" name="reference" maxlength="100" class="form-control" value="{{ old('reference') }}">
				</div>
				<div class="col-md-6">
					<label class="form-label" for="renewal-note">{{ __('platform.note') }}</label>
					<input id="renewal-note" type="text" name="note" maxlength="500" class="form-control" value="{{ old('note') }}">
				</div>
				<div class="col-md-6 d-flex align-items-end justify-content-between">
					<strong>{{ __('platform.amount_due') }}: <span id="renewal-total" data-monthly="{{ $monthlyPrice ?? 0 }}" data-annual="{{ $annualPrice ?? 0 }}">{{ $monthlyPrice !== null ? \App\Support\Money::format($monthlyPrice, $currency) : ($annualPrice !== null ? \App\Support\Money::format($annualPrice, $currency) : __('platform.price_not_configured')) }}</span></strong>
					<button type="submit" class="btn btn-primary" @disabled($monthlyPrice === null && $annualPrice === null)>{{ __('platform.record_payment') }}</button>
				</div>
			</form>
		</div>
		<div class="card-body p-0 border-top">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr>
							<th>{{ __('platform.paid_on') }}</th>
							<th>{{ __('platform.period') }}</th>
							<th>{{ __('platform.plan') }}</th>
							<th>{{ __('platform.discount') }}</th>
							<th>{{ __('platform.method') }}</th>
							<th>{{ __('platform.reference') }}</th>
							<th>{{ __('platform.recorded_by') }}</th>
							<th class="text-end">{{ __('platform.amount') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($payments as $payment)
							<tr>
								<td>{{ $payment->paid_on->format('Y-m-d') }}</td>
								<td>{{ $payment->period_start->format('Y-m-d') }} → {{ $payment->period_end->format('Y-m-d') }} ({{ $payment->months }})</td>
								<td>{{ $payment->plan_name ?? ($payment->plan ? __('platform.plans.'.$payment->plan) : '—') }}</td>
								<td>{{ $payment->discount_amount > 0 ? \App\Support\Money::format($payment->discount_amount, $payment->currency).' · '.$payment->discount_reason : '—' }}</td>
								<td>{{ __('pos.methods.'.$payment->method) }}</td>
								<td>{{ $payment->reference ?? '—' }}</td>
								<td>{{ $payment->user?->name ?? '—' }}</td>
									<td class="text-end">
										<div>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</div>
										@if ($payment->plan_price_amount !== null && $payment->plan_price_amount !== $payment->amount)
											<small class="text-muted">{{ __('platform.list_price') }}: {{ \App\Support\Money::format($payment->plan_price_amount, $payment->currency) }}</small>
										@endif
									</td>
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-4">{{ __('platform.no_payments') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<form method="POST" action="{{ route('platform.tenants.update', $tenant) }}">
		@csrf
		@method('PUT')
		@include('platform.tenants._form')
	</form>
</div>
@endsection

@push('extra-js')
<script>
	(function () {
		var period = document.getElementById('renewal-months');
		var discount = document.getElementById('renewal-discount');
		var reason = document.getElementById('renewal-discount-reason');
		var total = document.getElementById('renewal-total');
		if (!period || !discount || !reason || !total) { return; }

		function updateTotal() {
			var selected = period.options[period.selectedIndex];
			var price = Number(selected.getAttribute('data-price') || 0);
			var reduction = Math.round(Number(discount.value || 0) * 100);
			discount.max = (price / 100).toFixed(2);
			reason.required = reduction > 0;
			total.textContent = price > 0 ? 'TZS ' + new Intl.NumberFormat('{{ app()->getLocale() === 'sw' ? 'sw-TZ' : 'en-TZ' }}', { minimumFractionDigits: 2 }).format(Math.max(price - reduction, 0) / 100) : '{{ __('platform.price_not_configured') }}';
		}

		period.addEventListener('change', updateTotal);
		discount.addEventListener('input', updateTotal);
		updateTotal();
	})();
</script>
@endpush
