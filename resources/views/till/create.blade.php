@extends('layouts.app')

@section('title', __('till.close').' - Odessa POS')

@section('content')
@php($money = fn (int $amount) => \App\Support\Money::format($amount, $currency))
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('till.close') }}</h4>
				<h6><a href="{{ route('till-closings.index') }}">{{ __('till.title') }}</a></h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body">
			<p class="text-muted">{{ __('till.period', ['from' => $figures['start']->format('Y-m-d H:i'), 'to' => $figures['end']->format('H:i')]) }}</p>
			<table class="table table-sm w-auto mb-4">
				<tbody>
					<tr><td>{{ __('till.opening_float') }}</td><td class="text-end">{{ $money($figures['opening_float']) }}</td></tr>
					<tr><td>{{ __('till.cash_sales') }}</td><td class="text-end">{{ $money($figures['cash_sales']) }}</td></tr>
					<tr><td>{{ __('till.cash_refunds') }}</td><td class="text-end">- {{ $money($figures['cash_refunds']) }}</td></tr>
					<tr class="border-top"><th>{{ __('till.expected') }}</th><th class="text-end">{{ $money($figures['expected_cash']) }}</th></tr>
				</tbody>
			</table>

			<form method="POST" action="{{ route('till-closings.store') }}">
				@csrf
				<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
				<div class="row">
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('till.counted') }} ({{ $currency }}) <span class="text-danger">*</span></label>
						<input type="number" step="0.01" min="0" name="counted_cash" class="form-control @error('counted_cash') is-invalid @enderror" value="{{ old('counted_cash') }}" required>
						@error('counted_cash')<div class="invalid-feedback">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-4 mb-3">
						<label class="form-label">{{ __('till.float_kept') }} ({{ $currency }}) <span class="text-danger">*</span></label>
						<input type="number" step="0.01" min="0" name="float_kept" class="form-control @error('float_kept') is-invalid @enderror" value="{{ old('float_kept', '0') }}" required>
						<small class="text-muted">{{ __('till.float_hint') }}</small>
						@error('float_kept')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
					</div>
				</div>
				<div class="mb-3">
					<label class="form-label">{{ __('till.note') }}</label>
					<textarea name="note" rows="2" maxlength="500" class="form-control">{{ old('note') }}</textarea>
				</div>
				<button type="submit" class="btn btn-primary">{{ __('till.confirm') }}</button>
				<a href="{{ route('till-closings.index') }}" class="btn btn-secondary ms-2">{{ __('app.cancel') }}</a>
			</form>
		</div>
	</div>
</div>
@endsection
