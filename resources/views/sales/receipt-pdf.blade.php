<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
	<meta charset="utf-8">
	<title>{{ $sale->number }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #212b36; }
		h1 { font-size: 20px; margin: 0 0 4px; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 5px 4px; text-align: left; }
		th { border-bottom: 1px solid #999; }
		.r { text-align: right; }
		.meta td { padding: 1px 0; }
		.totals { width: 55%; margin-left: 45%; margin-top: 12px; }
		.totals td { border: 0; }
		.strong { font-weight: bold; }
		.due { color: #b00020; }
		.muted { color: #666; }
	</style>
</head>
<body>
@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $sale->currency);
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp
	<h1>{{ $business }}</h1>
	<table class="meta">
		<tr><td>{{ __('pos.sales.number') }}: <span class="strong">{{ $sale->number }}</span></td><td class="r">{{ __('pos.sales.date') }}: {{ $sale->sold_at?->format('Y-m-d H:i') }}</td></tr>
		<tr><td>{{ __('pos.sales.customer') }}: {{ $sale->customer?->name ?? __('pos.sales.walk_in') }}</td><td class="r">{{ __('pos.sales.served_by') }}: {{ $sale->user?->name ?? '—' }}</td></tr>
	</table>

	<table style="margin-top: 14px;">
		<thead>
			<tr>
				<th>{{ __('pos.sales.item') }}</th>
				<th class="r">{{ __('pos.sales.qty') }}</th>
				<th class="r">{{ __('pos.sales.price') }}</th>
				<th class="r">{{ __('pos.sales.line_total') }}</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($sale->items as $item)
				<tr>
					<td>{{ $item->product_name }}</td>
					<td class="r">{{ $qty($item->quantity) }}</td>
					<td class="r">{{ $fmt($item->unit_price) }}</td>
					<td class="r">{{ $fmt($item->total) }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	<table class="totals">
		<tr><td>{{ __('pos.sales.subtotal') }}</td><td class="r">{{ $fmt($sale->subtotal) }}</td></tr>
		@if ($sale->discount_total > 0)
			<tr><td>{{ __('pos.sales.discount') }}</td><td class="r">-{{ $fmt($sale->discount_total) }}</td></tr>
		@endif
		<tr><td>{{ __('pos.sales.tax') }}</td><td class="r">{{ $fmt($sale->tax_total) }}</td></tr>
		<tr class="strong"><td>{{ __('pos.sales.total') }}</td><td class="r">{{ $fmt($sale->total) }}</td></tr>
		<tr><td>{{ __('pos.sales.paid') }}</td><td class="r">{{ $fmt($sale->amount_paid) }}</td></tr>
		@if ($sale->change_given > 0)
			<tr><td>{{ __('pos.sales.change') }}</td><td class="r">{{ $fmt($sale->change_given) }}</td></tr>
		@endif
		@if ($sale->balance_due > 0)
			<tr class="strong due"><td>{{ __('pos.sales.balance') }}</td><td class="r">{{ $fmt($sale->balance_due) }}</td></tr>
		@endif
	</table>

	@if ($sale->payments->isNotEmpty())
		<p class="strong" style="margin-top: 16px;">{{ __('pos.sales.payments') }}</p>
		<table>
			@foreach ($sale->payments as $payment)
				<tr>
					<td>{{ __('pos.methods.'.$payment->method) }} <span class="muted">{{ $payment->paid_at?->format('Y-m-d H:i') }}@if ($payment->reference) · {{ $payment->reference }}@endif</span></td>
					<td class="r">{{ $fmt($payment->amount) }}</td>
				</tr>
			@endforeach
		</table>
	@endif

	<p class="muted" style="margin-top: 24px; text-align: center;">{{ __('pos.sales.thank_you') }}</p>
</body>
</html>
