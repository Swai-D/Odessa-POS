@extends('layouts.auth')

@section('title', __('business_statement.verify.title').' - Odessa POS')

@section('content')
@php
	use App\Support\Money;
	$fmt = fn (int $amount): string => Money::format($amount, $statement?->currency);
@endphp
<div class="main-wrapper">
	<div class="account-content">
		<div class="login-wrapper bg-img">
			<div class="login-content authent-content">
				<div class="login-userset">
					<div class="text-center mb-4">
						<h2 class="fw-bold mb-1">Odessa POS</h2>
						<p class="text-muted mb-0">{{ __('business_statement.verify.title') }}</p>
					</div>

					<form method="GET" action="{{ route('statements.verify') }}" class="mb-3">
						<label class="form-label" for="verify-code">{{ __('business_statement.doc.code') }}</label>
						<div class="input-group">
							<input id="verify-code" type="text" name="code" value="{{ $input }}" class="form-control" maxlength="30" autocomplete="off" placeholder="XXXX-XXXX-XXXX" required autofocus>
							<button type="submit" class="btn btn-primary">{{ __('business_statement.verify.check') }}</button>
						</div>
						<div class="form-text">{{ __('business_statement.verify.intro') }}</div>
					</form>

					@if ($input !== '' && $statement === null)
						<div class="alert alert-danger" role="alert">{{ __('business_statement.verify.not_found') }}</div>
					@endif

					@if ($statement !== null)
						@if ($intact)
							<div class="alert alert-success" role="alert"><strong>{{ __('business_statement.verify.valid') }}</strong><br>{{ __('business_statement.verify.valid_hint') }}</div>
							<table class="table table-sm">
								<tr><td class="text-muted">{{ __('business_statement.doc.heading') }}</td><td class="text-end">{{ $statement->number }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.verify.shop') }}</td><td class="text-end">{{ $statement->shop_name }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.doc.issued') }}</td><td class="text-end">{{ $statement->generated_at->format('Y-m-d') }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.doc.period') }}</td><td class="text-end">{{ $statement->period_from->format('Y-m-d') }} – {{ $statement->period_to->format('Y-m-d') }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.doc.net_sales') }}</td><td class="text-end fw-bold">{{ $fmt($statement->data['totals']['net_sales']) }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.doc.net_profit') }}</td><td class="text-end fw-bold">{{ $fmt($statement->data['totals']['net_profit']) }}</td></tr>
								<tr><td class="text-muted">{{ __('business_statement.doc.avg_monthly_sales') }}</td><td class="text-end">{{ $fmt($statement->data['averages']['monthly_net_sales']) }}</td></tr>
							</table>
							<p class="text-muted small">{{ __('business_statement.verify.compare') }}</p>
						@else
							<div class="alert alert-danger" role="alert"><strong>{{ __('business_statement.verify.altered') }}</strong></div>
						@endif
					@endif

					<p class="text-muted small text-center mt-3 mb-0">{{ __('business_statement.doc.disclaimer') }}</p>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
