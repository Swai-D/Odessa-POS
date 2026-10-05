@extends('layouts.app')

@section('title', __('pos.sales.title').' - Odessa POS')

@include('partials.datatable-assets')

@php
	use App\Support\Money;
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('pos.sales.title') }}</h4>
				<h6>{{ __('pos.sales.subtitle') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('sales.index', array_filter(['status' => $status])) }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			<div class="search-set">
				<div class="search-input">
					<span class="btn-searchset"><i class="ti ti-search fs-14 feather-search"></i></span>
				</div>
			</div>
			<div class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3">
				<div class="dropdown">
					<a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
						{{ $status === 'unpaid' ? __('pos.sales.unpaid_only') : __('pos.sales.all') }}
					</a>
					<ul class="dropdown-menu dropdown-menu-end p-3">
						<li><a href="{{ route('sales.index') }}" class="dropdown-item rounded-1">{{ __('pos.sales.all') }}</a></li>
						<li><a href="{{ route('sales.index', ['status' => 'unpaid']) }}" class="dropdown-item rounded-1">{{ __('pos.sales.unpaid_only') }}</a></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table datatable">
					<thead class="thead-light">
						<tr>
							<th>{{ __('pos.sales.number') }}</th>
							<th>{{ __('pos.sales.date') }}</th>
							<th>{{ __('pos.sales.customer') }}</th>
							<th>{{ __('pos.sales.cashier') }}</th>
							<th>{{ __('pos.sales.total') }}</th>
							<th>{{ __('pos.sales.paid') }}</th>
							<th>{{ __('pos.sales.balance') }}</th>
							<th>{{ __('pos.sales.status') }}</th>
							<th class="no-sort"></th>
						</tr>
					</thead>
					<tbody>
						@foreach ($sales as $sale)
							<tr>
								<td><a href="{{ route('sales.show', $sale) }}">{{ $sale->number }}</a></td>
								<td>{{ $sale->sold_at?->format('Y-m-d H:i') }}</td>
								<td>{{ $sale->customer?->name ?? __('pos.sales.walk_in') }}</td>
								<td>{{ $sale->user?->name ?? '—' }}</td>
								<td>{{ Money::format($sale->total, $sale->currency) }}</td>
								<td>{{ Money::format($sale->amount_paid, $sale->currency) }}</td>
								<td>{{ Money::format($sale->balance_due, $sale->currency) }}</td>
								<td>
									<span class="badge table-badge {{ ['paid' => 'bg-success', 'partial' => 'bg-warning', 'unpaid' => 'bg-danger'][$sale->payment_status] ?? 'bg-secondary' }} fw-medium fs-10">
										{{ __('pos.sales.statuses.'.$sale->payment_status) }}
									</span>
								</td>
								<td class="action-table-data">
									<div class="edit-delete-action">
										<a class="me-2 p-2" href="{{ route('sales.show', $sale) }}"><i data-feather="eye" class="feather-eye"></i></a>
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
