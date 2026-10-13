@extends('layouts.app')

@section('title', __('stocktake.new').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('stocktake.new') }}</h4>
				<h6>{{ __('stocktake.new_hint') }}</h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	<form method="POST" action="{{ route('stocktakes.store') }}" class="card" data-stocktake-form>
		@csrf
		<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
		<div class="card-body row g-3">
			<div class="col-md-4">
				<label class="form-label" for="st-warehouse">{{ __('stocktake.warehouse') }}</label>
				<select id="st-warehouse" name="warehouse_id" class="form-select" required>
					@foreach ($warehouses as $id => $name)
						<option value="{{ $id }}" @selected((int) old('warehouse_id') === $id)>{{ $name }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="st-category">{{ __('stocktake.category') }}</label>
				<select id="st-category" name="category_id" class="form-select">
					<option value="">{{ __('stocktake.all_products') }}</option>
					@foreach ($categories as $id => $name)
						<option value="{{ $id }}" @selected((int) old('category_id') === $id)>{{ $name }}</option>
					@endforeach
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label" for="st-note">{{ __('stocktake.note') }}</label>
				<input id="st-note" type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
			</div>
			<div class="col-12">
				<div class="alert alert-info mb-0">{{ __('stocktake.tip') }}</div>
			</div>
			<div class="col-12 d-flex gap-2">
				<button type="submit" class="btn btn-primary">{{ __('stocktake.start') }}</button>
				<a href="{{ route('stocktakes.index') }}" class="btn btn-outline-secondary">{{ __('stocktake.back') }}</a>
			</div>
		</div>
	</form>
</div>
@endsection
