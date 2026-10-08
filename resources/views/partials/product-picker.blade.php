{{--
	Searchable product field. Variables: $name, $tracked (bool), $selected (['id' => , 'label' => ] or null), $required (bool), $id (unique).
--}}
@php($pickerId = $id ?? 'picker-'.\Illuminate\Support\Str::random(6))
<div class="js-product-picker" data-url="{{ route('products.lookup') }}" data-tracked="{{ ($tracked ?? false) ? 1 : 0 }}" data-required="{{ ($required ?? true) ? 1 : 0 }}" data-message="{{ __('catalog.pick_product') }}">
	<input type="text" class="form-control" list="{{ $pickerId }}" value="{{ $selected['label'] ?? '' }}" placeholder="{{ __('catalog.search_product') }}" autocomplete="off">
	<datalist id="{{ $pickerId }}"></datalist>
	<input type="hidden" name="{{ $name }}" value="{{ $selected['id'] ?? '' }}">
</div>
