@extends('layouts.app')

@section('title', __('catalog.product_list').' - Odessa POS')


@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('catalog.product_list') }}</h4>
				<h6>{{ __('catalog.manage_products') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('catalog.import.export') }}" href="{{ route('products.export') }}"><i class="ti ti-file-export"></i></a></li>
			@if ($canManage)
				<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('catalog.import.title') }}" href="{{ route('products.import') }}"><i class="ti ti-file-import"></i></a></li>
			@endif
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('products.index') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
		@if ($canManage)
			<div class="page-btn">
				<a href="{{ route('products.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('catalog.add_product') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<!-- /product list -->
	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
			<div class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3">
				<div class="dropdown me-2">
					<a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
						{{ __('catalog.fields.category') }}
					</a>
					<ul class="dropdown-menu dropdown-menu-end p-3">
						<li><a href="{{ route('products.index', array_filter(['brand' => request('brand')])) }}" class="dropdown-item rounded-1">{{ __('app.all') }}</a></li>
						@foreach ($categories as $category)
							<li><a href="{{ route('products.index', array_filter(['category' => $category->id, 'brand' => request('brand')])) }}" class="dropdown-item rounded-1">{{ $category->name }}</a></li>
						@endforeach
					</ul>
				</div>
				<div class="dropdown">
					<a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
						{{ __('catalog.fields.brand') }}
					</a>
					<ul class="dropdown-menu dropdown-menu-end p-3">
						<li><a href="{{ route('products.index', array_filter(['category' => request('category')])) }}" class="dropdown-item rounded-1">{{ __('app.all') }}</a></li>
						@foreach ($brands as $brand)
							<li><a href="{{ route('products.index', array_filter(['brand' => $brand->id, 'category' => request('category')])) }}" class="dropdown-item rounded-1">{{ $brand->name }}</a></li>
						@endforeach
					</ul>
				</div>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>@include('partials.sort-header', ['label' => __('catalog.fields.sku'), 'key' => 'sku'])</th>
							<th>@include('partials.sort-header', ['label' => __('catalog.fields.name'), 'key' => 'name'])</th>
							<th>{{ __('catalog.fields.category') }}</th>
							<th>{{ __('catalog.fields.brand') }}</th>
							<th>@include('partials.sort-header', ['label' => __('catalog.fields.price'), 'key' => 'price'])</th>
							<th>{{ __('catalog.fields.unit') }}</th>
							<th>{{ __('catalog.fields.qty') }}</th>
							<th class="no-sort"></th>
						</tr>
					</thead>
					<tbody>
						@foreach ($products as $product)
							<tr>
								<td>{{ $product->sku }}</td>
								<td>
									{{ $product->name }}
									@unless ($product->is_active)
										<span class="badge bg-danger ms-1 fs-10">{{ __('catalog.inactive') }}</span>
									@endunless
								</td>
								<td>{{ $product->category?->name ?? '—' }}</td>
								<td>{{ $product->brand?->name ?? '—' }}</td>
								<td>{{ \App\Support\Money::format($product->selling_price) }}</td>
								<td>{{ $product->unit?->short_name ?? '—' }}</td>
								<td>
									@if ($product->track_stock)
										{{ rtrim(rtrim(number_format($product->totalQuantity(), 3, '.', ''), '0'), '.') }}
										@if ($product->isLowStock())
											<span class="badge bg-warning ms-1 fs-10">{{ __('catalog.low_stock') }}</span>
										@endif
									@else
										—
									@endif
								</td>
								<td class="action-table-data">
									@if ($canManage)
										<div class="edit-delete-action">
											<a class="me-2 p-2" href="{{ route('products.edit', $product) }}">
												<i data-feather="edit" class="feather-edit"></i>
											</a>
											<a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);"
												data-delete-action="{{ route('products.destroy', $product) }}">
												<i data-feather="trash-2" class="feather-trash-2"></i>
											</a>
										</div>
									@endif
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $products])
		</div>
	</div>
	<!-- /product list -->
</div>
@endsection

@push('modals')
@include('partials.delete-modal')
@endpush
