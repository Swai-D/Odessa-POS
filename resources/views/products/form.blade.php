@extends('layouts.app')

@php($editing = $product->exists)
@section('title', ($editing ? __('catalog.edit_product') : __('catalog.add_product')).' - Odessa POS')

@php
	$old = fn (string $key, mixed $default = null) => old($key, $product->{$key} ?? $default);
	$checked = fn (string $key) => (bool) old($key, $product->{$key});
	$money = fn (string $key) => old($key, \App\Support\Money::toMajor((int) $product->{$key}));
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $editing ? __('catalog.edit_product') : __('catalog.add_product') }}</h4>
				<h6>{{ __('app.required_hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('products.index') }}" class="btn btn-primary"><i data-feather="arrow-left" class="me-2"></i>{{ __('catalog.product_list') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}" class="add-product-form">
		@csrf
		@if ($editing) @method('PUT') @endif

		<div class="add-product">
			<!-- Product information -->
			<div class="card mb-4">
				<div class="card-header"><h5 class="d-flex align-items-center mb-0"><i data-feather="info" class="text-primary me-2"></i><span>{{ __('catalog.product_information') }}</span></h5></div>
				<div class="card-body">
					<div class="row">
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.name') }}<span class="text-danger ms-1">*</span></label>
								<input type="text" name="name" class="form-control" value="{{ $old('name') }}" required>
							</div>
						</div>
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.type') }}<span class="text-danger ms-1">*</span></label>
								<select name="type" class="form-select">
									@foreach (['standard', 'service'] as $type)
										<option value="{{ $type }}" @selected($old('type') === $type)>{{ __('catalog.types.'.$type) }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.sku') }}<span class="text-danger ms-1">*</span></label>
								<input type="text" name="sku" class="form-control" value="{{ $old('sku') }}" required>
							</div>
						</div>
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.barcode') }}</label>
								<input type="text" name="barcode" class="form-control" value="{{ $old('barcode') }}">
							</div>
						</div>
						@foreach ([['category_id', 'category', $categories], ['brand_id', 'brand', $brands], ['unit_id', 'unit', $units]] as [$field, $label, $options])
							<div class="col-sm-4 col-12">
								<div class="mb-3">
									<label class="form-label">{{ __('catalog.fields.'.$label) }}</label>
									<select name="{{ $field }}" class="form-select">
										<option value="">{{ __('catalog.select') }}</option>
										@foreach ($options as $id => $name)
											<option value="{{ $id }}" @selected((string) $old($field) === (string) $id)>{{ $name }}</option>
										@endforeach
									</select>
								</div>
							</div>
						@endforeach
						<div class="col-12">
							<div class="mb-0">
								<label class="form-label">{{ __('catalog.fields.description') }}</label>
								<textarea name="description" class="form-control" rows="3">{{ $old('description') }}</textarea>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Pricing & tax -->
			<div class="card mb-4">
				<div class="card-header"><h5 class="d-flex align-items-center mb-0"><i data-feather="dollar-sign" class="text-primary me-2"></i><span>{{ __('catalog.pricing_tax') }}</span></h5></div>
				<div class="card-body">
					<div class="row">
						<div class="col-sm-4 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.cost_price') }}<span class="text-danger ms-1">*</span></label>
								<input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="{{ $money('cost_price') }}" required>
							</div>
						</div>
						<div class="col-sm-4 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.selling_price') }}<span class="text-danger ms-1">*</span></label>
								<input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="{{ $money('selling_price') }}" required>
							</div>
						</div>
						<div class="col-sm-4 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.tax_rate') }}<span class="text-danger ms-1">*</span></label>
								<input type="number" step="0.01" min="0" max="100" name="tax_rate" class="form-control" value="{{ $old('tax_rate', 0) }}" required>
							</div>
						</div>
						<div class="col-12">
							<div class="status-toggle modal-status d-flex justify-content-between align-items-center">
								<span class="status-label">{{ __('catalog.fields.tax_inclusive') }}</span>
								<input type="hidden" name="tax_inclusive" value="0">
								<input type="checkbox" id="tax_inclusive" name="tax_inclusive" value="1" class="check" @checked($checked('tax_inclusive'))>
								<label for="tax_inclusive" class="checktoggle"></label>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Stock & options -->
			<div class="card mb-4">
				<div class="card-header"><h5 class="d-flex align-items-center mb-0"><i data-feather="list" class="text-primary me-2"></i><span>{{ __('catalog.stock_options') }}</span></h5></div>
				<div class="card-body">
					<div class="row">
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<div class="status-toggle modal-status d-flex justify-content-between align-items-center">
									<span class="status-label">{{ __('catalog.fields.track_stock') }}</span>
									<input type="hidden" name="track_stock" value="0">
									<input type="checkbox" id="track_stock" name="track_stock" value="1" class="check" @checked($checked('track_stock'))>
									<label for="track_stock" class="checktoggle"></label>
								</div>
							</div>
						</div>
						<div class="col-sm-6 col-12">
							<div class="mb-3">
								<div class="status-toggle modal-status d-flex justify-content-between align-items-center">
									<span class="status-label">{{ __('catalog.fields.status') }}</span>
									<input type="hidden" name="is_active" value="0">
									<input type="checkbox" id="is_active" name="is_active" value="1" class="check" @checked($checked('is_active'))>
									<label for="is_active" class="checktoggle"></label>
								</div>
							</div>
						</div>
						<div class="col-sm-4 col-12">
							<div class="mb-3">
								<label class="form-label">{{ __('catalog.fields.alert_quantity') }}</label>
								<input type="number" step="0.001" min="0" name="alert_quantity" class="form-control" value="{{ $old('alert_quantity', 0) }}">
							</div>
						</div>
						@unless ($editing)
							<div class="col-sm-4 col-12">
								<div class="mb-3">
									<label class="form-label">{{ __('catalog.fields.opening_quantity') }}</label>
									<input type="number" step="0.001" min="0" name="opening_quantity" class="form-control" value="{{ old('opening_quantity') }}">
								</div>
							</div>
							<div class="col-sm-4 col-12">
								<div class="mb-3">
									<label class="form-label">{{ __('catalog.fields.warehouse') }}</label>
									<select name="warehouse_id" class="form-select">
										<option value="">{{ __('catalog.select') }}</option>
										@foreach ($warehouses as $id => $name)
											<option value="{{ $id }}" @selected((string) old('warehouse_id') === (string) $id)>{{ $name }}</option>
										@endforeach
									</select>
									@if ($warehouses->isEmpty())
										<small class="text-muted">{{ __('catalog.no_warehouse_hint') }}</small>
									@endif
								</div>
							</div>
						@endunless
					</div>
				</div>
			</div>

			<!-- Optional features: shown only when this tenant enabled them -->
			@if ($settings->feature('weighed_products') || $settings->feature('batch_tracking') || $settings->feature('expiry_tracking'))
				<div class="card mb-4">
					<div class="card-header"><h5 class="d-flex align-items-center mb-0"><i data-feather="sliders" class="text-primary me-2"></i><span>{{ __('catalog.optional_features') }}</span></h5></div>
					<div class="card-body">
						@foreach ([['weighed_products', 'is_weighed'], ['batch_tracking', 'track_batch'], ['expiry_tracking', 'track_expiry']] as [$feature, $field])
							@if ($settings->feature($feature))
								<div class="mb-3">
									<div class="status-toggle modal-status d-flex justify-content-between align-items-center">
										<span class="status-label">{{ __('catalog.fields.'.$field) }}</span>
										<input type="hidden" name="{{ $field }}" value="0">
										<input type="checkbox" id="{{ $field }}" name="{{ $field }}" value="1" class="check" @checked($checked($field))>
										<label for="{{ $field }}" class="checktoggle"></label>
									</div>
								</div>
							@else
								<input type="hidden" name="{{ $field }}" value="{{ (int) $checked($field) }}">
							@endif
						@endforeach
					</div>
				</div>
			@else
				<input type="hidden" name="is_weighed" value="{{ (int) $checked('is_weighed') }}">
				<input type="hidden" name="track_batch" value="{{ (int) $checked('track_batch') }}">
				<input type="hidden" name="track_expiry" value="{{ (int) $checked('track_expiry') }}">
			@endif
		</div>

		<div class="d-flex align-items-center justify-content-end mb-4">
			<a href="{{ route('products.index') }}" class="btn btn-secondary me-2">{{ __('app.cancel') }}</a>
			<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
		</div>
	</form>
</div>
@endsection
