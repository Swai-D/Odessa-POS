@extends('layouts.app')

@section('title', $purchase->number.' - Odessa POS')

@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $purchase->currency);
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $purchase->number }}</h4>
				<h6><a href="{{ route('purchases.index') }}">{{ __('purchases.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-3"><div class="text-muted">{{ __('purchases.supplier') }}</div>{{ $purchase->supplier?->name }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('purchases.date') }}</div>{{ $purchase->purchased_at?->format('Y-m-d H:i') }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('purchases.warehouse') }}</div>{{ $purchase->warehouse?->name }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('purchases.reference') }}</div>{{ $purchase->reference ?? '—' }}</div>
			</div>

			<table class="table table-sm">
				<thead>
					<tr>
						<th>{{ __('purchases.product') }}</th>
						<th class="text-end">{{ __('purchases.qty') }}</th>
						<th class="text-end">{{ __('purchases.unit_cost') }}</th>
						<th class="text-end">{{ __('purchases.line_total') }}</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($purchase->items as $item)
						<tr>
							<td>{{ $item->product_name }} <small class="text-muted">{{ $item->sku }}</small></td>
							<td class="text-end">{{ $qty($item->quantity) }}</td>
							<td class="text-end">{{ $fmt($item->unit_cost) }}</td>
							<td class="text-end">{{ $fmt($item->total) }}</td>
						</tr>
					@endforeach
				</tbody>
			</table>

			<table class="w-100">
				<tr class="fw-bold"><td>{{ __('purchases.total') }}</td><td class="text-end">{{ $fmt($purchase->total) }}</td></tr>
				<tr><td>{{ __('purchases.paid') }}</td><td class="text-end">{{ $fmt($purchase->amount_paid) }}</td></tr>
				@if ($purchase->balance_due > 0)
					<tr class="fw-bold text-danger"><td>{{ __('purchases.balance') }}</td><td class="text-end">{{ $fmt($purchase->balance_due) }}</td></tr>
				@endif
			</table>

			@if ($purchase->note)
				<p class="mt-3 mb-0"><span class="text-muted">{{ __('purchases.note') }}:</span> {{ $purchase->note }}</p>
			@endif
		</div>
	</div>

	@if ($purchase->payments->isNotEmpty())
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('purchases.payments') }}</h5></div>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<tbody>
						@foreach ($purchase->payments as $payment)
							<tr>
								<td>{{ $payment->paid_at?->format('Y-m-d H:i') }}</td>
								<td>{{ __('pos.methods.'.$payment->method) }}@if ($payment->reference) <small class="text-muted">· {{ $payment->reference }}</small>@endif</td>
								<td>{{ $payment->user?->name ?? '—' }}</td>
								<td class="text-end">{{ $fmt($payment->amount) }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@endif

	@if ($canPay && $purchase->balance_due > 0)
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('purchases.record_payment') }} ({{ __('purchases.balance') }}: {{ $fmt($purchase->balance_due) }})</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('purchases.payments.store', $purchase) }}" class="row g-3">
					@csrf
					<div class="col-md-4">
						<label class="form-label">{{ __('purchases.method') }}</label>
						<select name="method" class="form-select" required>
							@foreach ($methods as $method)
								<option value="{{ $method }}" @selected(old('method') === $method)>{{ __('pos.methods.'.$method) }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label">{{ __('purchases.amount') }}</label>
						<input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', Money::toMajor($purchase->balance_due)) }}" required>
					</div>
					<div class="col-md-4">
						<label class="form-label">{{ __('purchases.payment_reference') }}</label>
						<input type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}">
					</div>
					<div class="col-12"><button type="submit" class="btn btn-primary">{{ __('purchases.record_payment') }}</button></div>
				</form>
			</div>
		</div>
	@endif
</div>
@endsection
