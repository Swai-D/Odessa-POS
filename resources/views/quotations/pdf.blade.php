<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
	<meta charset="utf-8">
	<title>{{ $quotation->number }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #212b36; }
		h1 { font-size: 20px; margin: 0 0 4px; }
		h2 { font-size: 16px; margin: 0 0 10px; color: #444; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 5px 4px; text-align: left; }
		th { border-bottom: 1px solid #999; }
		.r { text-align: right; }
		.meta td { padding: 1px 0; }
		.totals { width: 55%; margin-left: 45%; margin-top: 12px; }
		.strong { font-weight: bold; }
		.muted { color: #666; }
	</style>
</head>
<body>
@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $quotation->currency);
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp
	<h1>{{ $business }}</h1>
	<h2>{{ __('quotations.document_title') }}</h2>
	<table class="meta">
		<tr><td>{{ __('quotations.number') }}: <span class="strong">{{ $quotation->number }}</span></td><td class="r">{{ __('quotations.date') }}: {{ $quotation->quoted_at?->format('Y-m-d') }}</td></tr>
		<tr><td>{{ __('quotations.customer') }}: {{ $quotation->displayName() ?: '—' }}</td><td class="r">{{ __('quotations.valid_until') }}: {{ $quotation->valid_until?->format('Y-m-d') ?? '—' }}</td></tr>
	</table>
	<br>
	<table>
		<thead>
			<tr><th>{{ __('quotations.product') }}</th><th class="r">{{ __('quotations.qty') }}</th><th class="r">{{ __('quotations.unit_price') }}</th><th class="r">{{ __('quotations.line_total') }}</th></tr>
		</thead>
		<tbody>
			@foreach ($quotation->items as $item)
				<tr><td>{{ $item->product_name }}</td><td class="r">{{ $qty($item->quantity) }}</td><td class="r">{{ $fmt($item->unit_price) }}</td><td class="r">{{ $fmt($item->total) }}</td></tr>
			@endforeach
		</tbody>
	</table>
	<table class="totals">
		<tr><td>{{ __('quotations.subtotal') }}</td><td class="r">{{ $fmt($quotation->subtotal) }}</td></tr>
		@if ($quotation->discount_total > 0)
			<tr><td>{{ __('quotations.discount') }}</td><td class="r">-{{ $fmt($quotation->discount_total) }}</td></tr>
		@endif
		<tr><td>{{ __('quotations.tax') }}</td><td class="r">{{ $fmt($quotation->tax_total) }}</td></tr>
		<tr class="strong"><td>{{ __('quotations.total') }}</td><td class="r">{{ $fmt($quotation->total) }}</td></tr>
	</table>
	@if ($quotation->note)
		<p class="muted">{{ $quotation->note }}</p>
	@endif
	<p class="muted">{{ __('quotations.pdf_footer') }}</p>
</body>
</html>
