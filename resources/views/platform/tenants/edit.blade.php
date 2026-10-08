@extends('layouts.app')

@section('title', __('platform.edit').' - Odessa POS')

@section('content')
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
		</div>
		<div class="card-body">
			<form method="POST" action="{{ route('platform.tenants.renewals.store', $tenant) }}" class="row g-3">
				@csrf
				<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
				<div class="col-md-2">
					<label class="form-label">{{ __('platform.months') }}</label>
					<input type="number" name="months" min="1" max="24" class="form-control" value="{{ old('months', 1) }}" required>
				</div>
				<div class="col-md-3">
					<label class="form-label">{{ __('platform.amount') }} ({{ $currency }})</label>
					<input type="number" name="amount" min="0" step="0.01" class="form-control" value="{{ old('amount') }}" required>
				</div>
				<div class="col-md-3">
					<label class="form-label">{{ __('platform.method') }}</label>
					<select name="method" class="form-select">
						@foreach ($methods as $method)
							<option value="{{ $method }}" @selected(old('method', 'mobile_money') === $method)>{{ __('pos.methods.'.$method) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label">{{ __('platform.paid_on') }}</label>
					<input type="date" name="paid_on" class="form-control" max="{{ now()->format('Y-m-d') }}" value="{{ old('paid_on', now()->format('Y-m-d')) }}" required>
				</div>
				<div class="col-md-2">
					<label class="form-label">{{ __('platform.reference') }}</label>
					<input type="text" name="reference" maxlength="100" class="form-control" value="{{ old('reference') }}">
				</div>
				<div class="col-md-10">
					<label class="form-label">{{ __('platform.note') }}</label>
					<input type="text" name="note" maxlength="500" class="form-control" value="{{ old('note') }}">
				</div>
				<div class="col-md-2 d-flex align-items-end">
					<button type="submit" class="btn btn-primary w-100">{{ __('platform.record_payment') }}</button>
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
								<td>{{ $payment->plan ? __('platform.plans.'.$payment->plan) : '—' }}</td>
								<td>{{ __('pos.methods.'.$payment->method) }}</td>
								<td>{{ $payment->reference ?? '—' }}</td>
								<td>{{ $payment->user?->name ?? '—' }}</td>
								<td class="text-end">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center text-muted py-4">{{ __('platform.no_payments') }}</td></tr>
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
