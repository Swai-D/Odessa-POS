@extends('layouts.app')

@section('title', $sale->number.' - Odessa POS')

@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $sale->currency);
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

@push('extra-css')
<style>
	.receipt { background: #fff; color: #212b36; margin: 0 auto; padding: 24px; border: 1px solid #e9edf4; border-radius: 8px; }
	.receipt-a4 { max-width: 820px; }
	.receipt-thermal { max-width: 302px; padding: 12px; font-size: 12px; }
	.receipt-thermal table { font-size: 12px; }
	.receipt h5, .receipt h6 { margin-bottom: 2px; }
	.receipt .totals td { padding: 2px 0; }
	@media print {
		body * { visibility: hidden; }
		.receipt, .receipt * { visibility: visible; }
		.receipt { position: absolute; left: 0; top: 0; width: 100%; border: 0; }
		.receipt-thermal { width: 80mm; max-width: 80mm; }
	}
</style>
@endpush

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('pos.sales.receipt') }} {{ $sale->number }}</h4>
				<h6><a href="{{ route('sales.index') }}">{{ __('pos.sales.back') }}</a></h6>
			</div>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<a class="btn btn-white {{ $format === 'a4' ? 'active' : '' }}" href="{{ route('sales.show', [$sale, 'format' => 'a4']) }}">{{ __('pos.sales.a4') }}</a>
			<a class="btn btn-white {{ $format === 'thermal' ? 'active' : '' }}" href="{{ route('sales.show', [$sale, 'format' => 'thermal']) }}">{{ __('pos.sales.thermal') }}</a>
			<a class="btn btn-white" href="{{ route('sales.pdf', $sale) }}"><i class="ti ti-file-type-pdf me-1"></i>{{ __('pos.sales.pdf') }}</a>
			<button type="button" class="btn btn-primary" onclick="window.print()"><i class="ti ti-printer me-1"></i>{{ __('pos.sales.print') }}</button>
		</div>
	</div>

	@include('partials.flash')

	<div class="receipt receipt-{{ $format }} mb-4">
		<div class="text-center mb-3">
			<h5 class="fw-bold">{{ $business }}</h5>
			<div>{{ $sale->number }}</div>
			<div>{{ $sale->sold_at?->format('Y-m-d H:i') }}</div>
			<div>{{ __('pos.sales.customer') }}: {{ $sale->customer?->name ?? __('pos.sales.walk_in') }}</div>
			<div>{{ __('pos.sales.served_by') }}: {{ $sale->user?->name ?? '—' }}</div>
		</div>

		<table class="table table-sm">
			<thead>
				<tr>
					<th>{{ __('pos.sales.item') }}</th>
					<th class="text-end">{{ __('pos.sales.qty') }}</th>
					<th class="text-end">{{ __('pos.sales.price') }}</th>
					<th class="text-end">{{ __('pos.sales.line_total') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($sale->items as $item)
					<tr>
						<td>{{ $item->product_name }}</td>
						<td class="text-end">{{ $qty($item->quantity) }}</td>
						<td class="text-end">{{ $fmt($item->unit_price) }}</td>
						<td class="text-end">{{ $fmt($item->total) }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>

		<table class="totals w-100">
			<tr><td>{{ __('pos.sales.subtotal') }}</td><td class="text-end">{{ $fmt($sale->subtotal) }}</td></tr>
			@if ($sale->discount_total > 0)
				<tr><td>{{ __('pos.sales.discount') }}</td><td class="text-end">-{{ $fmt($sale->discount_total) }}</td></tr>
			@endif
			<tr><td>{{ __('pos.sales.tax') }}</td><td class="text-end">{{ $fmt($sale->tax_total) }}</td></tr>
			<tr class="fw-bold"><td>{{ __('pos.sales.total') }}</td><td class="text-end">{{ $fmt($sale->total) }}</td></tr>
			<tr><td>{{ __('pos.sales.paid') }}</td><td class="text-end">{{ $fmt($sale->amount_paid) }}</td></tr>
			@if ($sale->change_given > 0)
				<tr><td>{{ __('pos.sales.change') }}</td><td class="text-end">{{ $fmt($sale->change_given) }}</td></tr>
			@endif
			@if ($sale->returned_total > 0)
				<tr><td>{{ __('pos.sales.returned') }}</td><td class="text-end">-{{ $fmt($sale->returned_total) }}</td></tr>
				<tr class="fw-bold"><td>{{ __('pos.sales.net_total') }}</td><td class="text-end">{{ $fmt($sale->total - $sale->returned_total) }}</td></tr>
			@endif
			@if ($sale->balance_due > 0)
				<tr class="fw-bold text-danger"><td>{{ __('pos.sales.balance') }}</td><td class="text-end">{{ $fmt($sale->balance_due) }}</td></tr>
			@endif
		</table>

		@if ($sale->payments->isNotEmpty())
			<hr>
			<h6>{{ __('pos.sales.payments') }}</h6>
			<table class="totals w-100">
				@foreach ($sale->payments as $payment)
					<tr>
						<td>{{ __('pos.methods.'.$payment->method) }} <small class="text-muted">{{ $payment->paid_at?->format('Y-m-d H:i') }}@if ($payment->reference) · {{ $payment->reference }}@endif</small></td>
						<td class="text-end">{{ $fmt($payment->amount) }}</td>
					</tr>
				@endforeach
			</table>
		@endif

		<p class="text-center mt-3 mb-0">{{ $footer !== '' ? $footer : __('pos.sales.thank_you') }}</p>
	</div>

	@if ($canPay && $sale->balance_due > 0)
		<div class="card" style="max-width: 820px; margin: 0 auto;">
			<div class="card-header"><h5 class="mb-0">{{ __('pos.sales.record_payment') }} ({{ __('pos.sales.balance') }}: {{ $fmt($sale->balance_due) }})</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('sales.payments.store', $sale) }}" class="row g-3">
					@csrf
					<div class="col-md-4">
						<label class="form-label">{{ __('pos.sales.method') }}</label>
						<select name="method" class="form-select" required>
							@foreach (\App\Domain\Sales\Models\Payment::methods() as $method)
								<option value="{{ $method }}" @selected(old('method') === $method)>{{ __('pos.methods.'.$method) }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-4">
						<label class="form-label">{{ __('pos.sales.amount') }}</label>
						<input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="{{ old('amount', Money::toMajor($sale->balance_due)) }}" required>
					</div>
					<div class="col-md-4">
						<label class="form-label">{{ __('pos.sales.reference') }}</label>
						<input type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}">
					</div>
					<div class="col-12">
						<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
					</div>
				</form>
			</div>
		</div>
	@endif
	@php
		$returnable = $sale->items->filter(fn ($item) => $item->remainingQuantity() > 0);
	@endphp
	@if ($canPay && $returnable->isNotEmpty())
		<div class="card" style="max-width: 820px; margin: 16px auto 0;">
			<div class="card-header"><h5 class="mb-0">{{ __('pos.sales.return_items') }}</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('sales.returns.store', $sale) }}">
					@csrf
					<div class="table-responsive">
						<table class="table table-sm align-middle">
							<thead>
								<tr>
									<th>{{ __('pos.sales.item') }}</th>
									<th class="text-end">{{ __('pos.sales.qty') }}</th>
									<th class="text-end">{{ __('pos.sales.returned') }}</th>
									<th style="width: 140px;">{{ __('pos.sales.return_qty') }}</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($returnable as $i => $item)
									<tr>
										<td>{{ $item->product_name }}</td>
										<td class="text-end">{{ $qty($item->quantity) }}</td>
										<td class="text-end">{{ $qty($item->returned_quantity) }}</td>
										<td>
											<input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}">
											<input type="number" class="form-control form-control-sm" name="items[{{ $i }}][quantity]" min="0" max="{{ $qty($item->remainingQuantity()) }}" step="any" value="{{ old('items.'.$i.'.quantity', 0) }}">
										</td>
									</tr>
								@endforeach
							</tbody>
						</table>
					</div>
					<div class="row g-3">
						<div class="col-md-4">
							<label class="form-label">{{ __('pos.sales.refund_method') }}</label>
							<select name="refund_method" class="form-select">
								<option value=""></option>
								@foreach (\App\Domain\Sales\Models\Payment::methods() as $method)
									<option value="{{ $method }}" @selected(old('refund_method') === $method)>{{ __('pos.methods.'.$method) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-8">
							<label class="form-label">{{ __('pos.sales.reason') }}</label>
							<input type="text" name="reason" class="form-control" maxlength="500" value="{{ old('reason') }}">
						</div>
					</div>
					<p class="text-muted small mt-2 mb-3">{{ __('pos.sales.refund_hint') }}</p>
					<button type="submit" class="btn btn-primary">{{ __('pos.sales.submit_return') }}</button>
				</form>
			</div>
		</div>
	@endif

	@if ($sale->returns->isNotEmpty())
		<div class="card" style="max-width: 820px; margin: 16px auto 0;">
			<div class="card-header"><h5 class="mb-0">{{ __('pos.sales.returns') }}</h5></div>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<thead>
						<tr>
							<th>{{ __('pos.sales.return_number') }}</th>
							<th>{{ __('pos.sales.date') }}</th>
							<th class="text-end">{{ __('pos.sales.total') }}</th>
							<th class="text-end">{{ __('pos.sales.credit_applied') }}</th>
							<th class="text-end">{{ __('pos.sales.refunded') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($sale->returns as $return)
							<tr>
								<td>{{ $return->number }}@if ($return->reason) <small class="text-muted">· {{ $return->reason }}</small>@endif</td>
								<td>{{ $return->returned_at?->format('Y-m-d H:i') }}</td>
								<td class="text-end">{{ $fmt($return->total) }}</td>
								<td class="text-end">{{ $fmt($return->credit_applied) }}</td>
								<td class="text-end">{{ $fmt($return->refunded) }}@if ($return->refund_method) <small class="text-muted">({{ __('pos.methods.'.$return->refund_method) }})</small>@endif</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	@endif
</div>
@endsection
