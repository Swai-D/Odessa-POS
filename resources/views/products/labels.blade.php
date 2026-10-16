@extends('layouts.app')

@section('title', __('labels.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('labels.title') }}</h4>
				<h6>{{ __('labels.subtitle') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('products.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-left me-1"></i>{{ __('labels.back') }}</a>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-7">
			<div class="card">
				<div class="card-body">
					<label class="form-label">{{ __('labels.add_product') }}</label>
					<div class="js-product-picker mb-3" data-url="{{ route('products.lookup') }}" data-required="0">
						<input type="text" class="form-control" id="label-search" list="label-search-list" placeholder="{{ __('catalog.search_product') }}" autocomplete="off">
						<datalist id="label-search-list"></datalist>
						<input type="hidden" id="label-picked">
					</div>
					<div class="table-responsive">
						<table class="table" id="label-table">
							<thead class="thead-light">
								<tr>
									<th>{{ __('catalog.fields.name') }}</th>
									<th>{{ __('labels.code') }}</th>
									<th style="width: 110px;">{{ __('labels.copies') }}</th>
									<th></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
					<p class="text-muted mb-2" id="label-empty">{{ __('labels.empty') }}</p>
					<button type="button" class="btn btn-outline-primary btn-sm" id="label-fill-stock"><i class="ti ti-stack-3 me-1"></i>{{ __('labels.fill_stock') }}</button>
					<button type="button" class="btn btn-outline-secondary btn-sm" id="label-clear">{{ __('labels.clear') }}</button>
					<div class="alert alert-warning mt-3 mb-0 d-none" id="label-warn"></div>
				</div>
			</div>
		</div>
		<div class="col-lg-5">
			<div class="card">
				<div class="card-body">
					<div class="mb-3">
						<label class="form-label" for="label-size">{{ __('labels.size') }}</label>
						<select class="form-select" id="label-size">
							<optgroup label="{{ __('labels.thermal') }}">
								<option value="t50x30">50 × 30 mm</option>
								<option value="t40x25">40 × 25 mm</option>
								<option value="t58x40">58 × 40 mm</option>
							</optgroup>
							<optgroup label="{{ __('labels.sheet') }}">
								<option value="a3x8">A4 · 3 × 8 (24)</option>
								<option value="a4x10">A4 · 4 × 10 (40)</option>
								<option value="a5x13">A4 · 5 × 13 (65)</option>
							</optgroup>
						</select>
					</div>
					<div class="mb-3" id="label-skip-wrap">
						<label class="form-label" for="label-skip">{{ __('labels.skip') }}</label>
						<input type="number" class="form-control" id="label-skip" min="0" max="64" value="0">
						<small class="text-muted">{{ __('labels.skip_hint') }}</small>
					</div>
					<div class="mb-3">
						<div class="form-check"><input class="form-check-input" type="checkbox" id="opt-shop" checked><label class="form-check-label" for="opt-shop">{{ __('labels.show_shop') }}</label></div>
						<div class="form-check"><input class="form-check-input" type="checkbox" id="opt-name" checked><label class="form-check-label" for="opt-name">{{ __('labels.show_name') }}</label></div>
						<div class="form-check"><input class="form-check-input" type="checkbox" id="opt-price" checked><label class="form-check-label" for="opt-price">{{ __('labels.show_price') }}</label></div>
						<div class="form-check"><input class="form-check-input" type="checkbox" id="opt-sku"><label class="form-check-label" for="opt-sku">{{ __('labels.show_sku') }}</label></div>
					</div>
					<p class="text-muted fs-12" id="label-total"></p>
					<button type="button" class="btn btn-primary w-100" id="label-print"><i class="ti ti-printer me-1"></i>{{ __('labels.print') }}</button>
					<small class="text-muted d-block mt-2">{{ __('labels.print_hint') }}</small>
				</div>
			</div>
			<div class="card">
				<div class="card-header"><h6 class="mb-0">{{ __('labels.preview') }}</h6></div>
				<div class="card-body" id="label-preview" style="overflow: auto;"></div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('extra-js')
<script src="{{ asset('js/product-picker.js') }}"></script>
<script src="{{ asset('js/barcode.js') }}"></script>
<script>
	window.labelsConfig = {
		business: @json($business),
		initial: @json($initial),
		i18n: {
			noCode: @json(__('labels.no_code')),
			remove: @json(__('labels.remove')),
			unreadable: @json(__('labels.unreadable')),
			total: @json(__('labels.total')),
			popup: @json(__('labels.print_failed'))
		}
	};
</script>
<script src="{{ asset('js/labels.js') }}"></script>
@endpush
