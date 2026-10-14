@php
	use App\Support\Money;
	$d = $statement->data;
	$t = $d['totals'];
	$a = $d['averages'];
	$p = $d['position'];
	$fmt = fn (int|float $amount): string => Money::format((int) $amount, $statement->currency);
	$monthLabel = fn (string $key): string => \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->locale($statement->locale)->translatedFormat('M Y');
	$verifyUrl = route('statements.verify');
@endphp
<div class="stmt">
	<table class="stmt-head">
		<tr>
			<td>
				<div class="stmt-title">{{ __('statement.doc.heading') }}</div>
				<div class="stmt-shop">{{ $statement->shop_name }}</div>
			</td>
			<td class="r">
				<div>{{ __('statement.doc.number') }}: <strong>{{ $statement->number }}</strong></div>
				<div>{{ __('statement.doc.issued') }}: {{ $statement->generated_at->format('Y-m-d') }}</div>
				<div>{{ __('statement.doc.period') }}: {{ $statement->period_from->format('Y-m-d') }} – {{ $statement->period_to->format('Y-m-d') }} ({{ __('statement.months', ['n' => $statement->months]) }})</div>
			</td>
		</tr>
	</table>

	<h3>{{ __('statement.doc.summary') }}</h3>
	<table class="stmt-grid">
		<tr><td>{{ __('statement.doc.net_sales') }}</td><td class="r strong">{{ $fmt($t['net_sales']) }}</td></tr>
		<tr><td>{{ __('statement.doc.avg_monthly_sales') }}</td><td class="r strong">{{ $fmt($a['monthly_net_sales']) }}</td></tr>
		<tr><td>{{ __('statement.doc.gross_profit') }}</td><td class="r">{{ $fmt($t['gross_profit']) }}</td></tr>
		<tr><td>{{ __('statement.doc.expenses') }}</td><td class="r">{{ $fmt($t['expenses']) }}</td></tr>
		<tr><td>{{ __('statement.doc.net_profit') }}</td><td class="r strong">{{ $fmt($t['net_profit']) }}</td></tr>
		<tr><td>{{ __('statement.doc.avg_monthly_profit') }}</td><td class="r">{{ $fmt($a['monthly_net_profit']) }}</td></tr>
		<tr><td>{{ __('statement.doc.transactions') }}</td><td class="r">{{ number_format($t['sales_count']) }}</td></tr>
		<tr><td>{{ __('statement.doc.avg_sale') }}</td><td class="r">{{ $fmt($a['average_sale']) }}</td></tr>
		<tr><td>{{ __('statement.doc.customers') }}</td><td class="r">{{ number_format($d['activity']['customers']) }}</td></tr>
		<tr><td>{{ __('statement.doc.active_months') }}</td><td class="r">{{ $a['active_months'] }} / {{ $statement->months }}</td></tr>
		<tr><td>{{ __('statement.doc.trading_since') }}</td><td class="r">{{ $d['activity']['first_sale_on'] ?? '—' }}</td></tr>
	</table>

	<h3>{{ __('statement.doc.monthly') }}</h3>
	<table class="stmt-table">
		<thead>
			<tr>
				<th>{{ __('statement.doc.month') }}</th>
				<th class="r">{{ __('statement.doc.sales_count') }}</th>
				<th class="r">{{ __('statement.doc.gross_sales') }}</th>
				<th class="r">{{ __('statement.doc.returns') }}</th>
				<th class="r">{{ __('statement.doc.net_sales') }}</th>
				<th class="r">{{ __('statement.doc.gross_profit') }}</th>
				<th class="r">{{ __('statement.doc.expenses') }}</th>
				<th class="r">{{ __('statement.doc.net_profit') }}</th>
				<th class="r">{{ __('statement.doc.purchases') }}</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($d['months'] as $row)
				<tr>
					<td>{{ $monthLabel($row['key']) }}</td>
					<td class="r">{{ number_format($row['sales_count']) }}</td>
					<td class="r">{{ $fmt($row['gross_sales']) }}</td>
					<td class="r">{{ $fmt($row['returns']) }}</td>
					<td class="r">{{ $fmt($row['net_sales']) }}</td>
					<td class="r">{{ $fmt($row['gross_profit']) }}</td>
					<td class="r">{{ $fmt($row['expenses']) }}</td>
					<td class="r">{{ $fmt($row['net_profit']) }}</td>
					<td class="r">{{ $fmt($row['purchases']) }}</td>
				</tr>
			@endforeach
			<tr class="total">
				<td>{{ __('statement.doc.total') }}</td>
				<td class="r">{{ number_format($t['sales_count']) }}</td>
				<td class="r">{{ $fmt($t['gross_sales']) }}</td>
				<td class="r">{{ $fmt($t['returns']) }}</td>
				<td class="r">{{ $fmt($t['net_sales']) }}</td>
				<td class="r">{{ $fmt($t['gross_profit']) }}</td>
				<td class="r">{{ $fmt($t['expenses']) }}</td>
				<td class="r">{{ $fmt($t['net_profit']) }}</td>
				<td class="r">{{ $fmt($t['purchases']) }}</td>
			</tr>
		</tbody>
	</table>

	<h3>{{ __('statement.doc.position', ['date' => $statement->generated_at->format('Y-m-d')]) }}</h3>
	<table class="stmt-grid">
		<tr><td>{{ __('statement.doc.receivables') }}</td><td class="r">{{ $fmt($p['receivables']) }}</td></tr>
		<tr><td>{{ __('statement.doc.payables') }}</td><td class="r">{{ $fmt($p['payables']) }}</td></tr>
		<tr><td>{{ __('statement.doc.stock_value') }}</td><td class="r">{{ $fmt($p['stock_value']) }}</td></tr>
	</table>

	@if (! empty($d['expense_categories']))
		<h3>{{ __('statement.doc.expense_breakdown') }}</h3>
		<table class="stmt-grid">
			@foreach ($d['expense_categories'] as $row)
				<tr><td>{{ $row['name'] }}</td><td class="r">{{ $fmt($row['amount']) }}</td></tr>
			@endforeach
		</table>
	@endif

	<div class="stmt-verify">
		<div class="strong">{{ __('statement.doc.verify_title') }}</div>
		<div>{{ __('statement.doc.verify_text', ['url' => $verifyUrl]) }}</div>
		<div class="stmt-code">{{ __('statement.doc.code') }}: {{ $statement->code }}</div>
	</div>

	<p class="stmt-note">{{ __('statement.doc.disclaimer') }}</p>
</div>
