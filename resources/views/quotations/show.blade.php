@extends('layouts.app')

@section('title', $quotation->number.' - Odessa POS')

@php
	use App\Support\Money;
	$fmt = fn (int $amount) => Money::format($amount, $quotation->currency);
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $quotation->number }}</h4>
				<h6><a href="{{ route('quotations.index') }}">{{ __('quotations.title') }}</a></h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('quotations.pdf', $quotation) }}" target="_blank" class="btn btn-white"><i class="ti ti-file-type-pdf me-1"></i>{{ __('quotations.pdf') }}</a>
		</div>
	</div>

	@include('partials.flash')

	@if ($errors->any())
		<div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
	@endif

	@if ($priceChanged)
		<div class="alert alert-warning">{{ __('quotations.price_changed', ['quoted' => $fmt($quotation->total), 'now' => $fmt((int) $currentTotal)]) }}</div>
	@endif

	<div class="card">
		<div class="card-body">
			<div class="row mb-3">
				<div class="col-md-3"><div class="text-muted">{{ __('quotations.customer') }}</div>{{ $quotation->displayName() ?: '—' }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('quotations.date') }}</div>{{ $quotation->quoted_at?->format('Y-m-d') }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('quotations.valid_until') }}</div>{{ $quotation->valid_until?->format('Y-m-d') ?? '—' }}</div>
				<div class="col-md-3"><div class="text-muted">{{ __('quotations.status') }}</div>
					{{ $quotation->isExpired() ? __('quotations.expired') : __('quotations.statuses.'.$quotation->status) }}
					@if ($quotation->sale)
						· <a href="{{ route('sales.show', $quotation->sale) }}">{{ $quotation->sale->number }}</a>
					@endif
				</div>
			</div>

			<table class="table table-sm">
				<thead>
					<tr>
						<th>{{ __('quotations.product') }}</th>
						<th class="text-end">{{ __('quotations.qty') }}</th>
						<th class="text-end">{{ __('quotations.unit_price') }}</th>
						<th class="text-end">{{ __('quotations.line_total') }}</th>
					</tr>
				</thead>
				<tbody>
					@foreach ($quotation->items as $item)
						<tr>
							<td>{{ $item->product_name }} <span class="text-muted">({{ $item->sku }})</span></td>
							<td class="text-end">{{ $qty($item->quantity) }}</td>
							<td class="text-end">{{ $fmt($item->unit_price) }}</td>
							<td class="text-end">{{ $fmt($item->total) }}</td>
						</tr>
					@endforeach
				</tbody>
				<tfoot>
					<tr><td colspan="3" class="text-end">{{ __('quotations.subtotal') }}</td><td class="text-end">{{ $fmt($quotation->subtotal) }}</td></tr>
					@if ($quotation->discount_total > 0)
						<tr><td colspan="3" class="text-end">{{ __('quotations.discount') }}</td><td class="text-end">-{{ $fmt($quotation->discount_total) }}</td></tr>
					@endif
					<tr><td colspan="3" class="text-end">{{ __('quotations.tax') }}</td><td class="text-end">{{ $fmt($quotation->tax_total) }}</td></tr>
					<tr><td colspan="3" class="text-end fw-bold">{{ __('quotations.total') }}</td><td class="text-end fw-bold">{{ $fmt($quotation->total) }}</td></tr>
				</tfoot>
			</table>
			@if ($quotation->note)
				<div class="text-muted">{{ $quotation->note }}</div>
			@endif
		</div>
	</div>

	@if ($canManage && $quotation->status !== 'converted')
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('quotations.mark_as') }}</h5></div>
			<div class="card-body d-flex flex-wrap gap-2">
				@foreach (['sent', 'accepted', 'declined'] as $s)
					<form method="POST" action="{{ route('quotations.status', $quotation) }}">
						@csrf
						<input type="hidden" name="status" value="{{ $s }}">
						<button class="btn btn-white" @disabled($quotation->status === $s)>{{ __('quotations.statuses.'.$s) }}</button>
					</form>
				@endforeach
			</div>
		</div>
	@endif

	@if ($canManage && $quotation->canConvert())
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('quotations.convert') }}</h5></div>
			<div class="card-body">
				<p class="text-muted">{{ __('quotations.convert_hint') }}</p>
				<form method="POST" action="{{ route('quotations.convert', $quotation) }}">
					@csrf
					<div class="row">
						<div class="col-md-3 mb-3">
							<label class="form-label">{{ __('quotations.warehouse') }}<span class="text-danger ms-1">*</span></label>
							<select name="warehouse_id" class="form-select" required>
								@foreach ($warehouses as $id => $name)
									<option value="{{ $id }}" @selected((string) old('warehouse_id') === (string) $id)>{{ $name }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3 mb-3">
							<label class="form-label">{{ __('quotations.paid_now') }}</label>
							<input type="number" step="0.01" min="0" name="payment_amount" class="form-control" value="{{ old('payment_amount') }}">
						</div>
						<div class="col-md-3 mb-3">
							<label class="form-label">{{ __('quotations.method') }}</label>
							<select name="payment_method" class="form-select">
								<option value=""></option>
								@foreach ($methods as $method)
									<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('pos.methods.'.$method) }}</option>
								@endforeach
							</select>
						</div>
						<div class="col-md-3 mb-3">
							<label class="form-label">{{ __('quotations.reference') }}</label>
							<input type="text" name="payment_reference" class="form-control" maxlength="100" value="{{ old('payment_reference') }}">
						</div>
					</div>
					<button type="submit" class="btn btn-primary">{{ __('quotations.convert_button') }}</button>
				</form>
			</div>
		</div>
	@endif
</div>
@endsection
