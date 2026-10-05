@extends('layouts.app')

@section('title', __('purchases.title').' - Odessa POS')

@include('partials.datatable-assets')

@php
	use App\Support\Money;
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('purchases.title') }}</h4>
				<h6>{{ __('purchases.subtitle') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('purchases.index', array_filter(['status' => $status])) }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
		@if ($canCreate)
			<div class="page-btn">
				<a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('purchases.new') }}</a>
			</div>
		@endif
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
						{{ $status === 'unpaid' ? __('purchases.unpaid_only') : __('purchases.all') }}
					</a>
					<ul class="dropdown-menu dropdown-menu-end p-3">
						<li><a href="{{ route('purchases.index') }}" class="dropdown-item rounded-1">{{ __('purchases.all') }}</a></li>
						<li><a href="{{ route('purchases.index', ['status' => 'unpaid']) }}" class="dropdown-item rounded-1">{{ __('purchases.unpaid_only') }}</a></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table datatable">
					<thead class="thead-light">
						<tr>
							<th>{{ __('purchases.number') }}</th>
							<th>{{ __('purchases.date') }}</th>
							<th>{{ __('purchases.supplier') }}</th>
							<th>{{ __('purchases.reference') }}</th>
							<th>{{ __('purchases.total') }}</th>
							<th>{{ __('purchases.paid') }}</th>
							<th>{{ __('purchases.balance') }}</th>
							<th>{{ __('purchases.status') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach ($purchases as $purchase)
							<tr>
								<td><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->number }}</a></td>
								<td>{{ $purchase->purchased_at?->format('Y-m-d H:i') }}</td>
								<td>{{ $purchase->supplier?->name }}</td>
								<td>{{ $purchase->reference ?? '—' }}</td>
								<td>{{ Money::format($purchase->total, $purchase->currency) }}</td>
								<td>{{ Money::format($purchase->amount_paid, $purchase->currency) }}</td>
								<td>{{ Money::format($purchase->balance_due, $purchase->currency) }}</td>
								<td>
									<span class="badge table-badge {{ ['paid' => 'bg-success', 'partial' => 'bg-warning', 'unpaid' => 'bg-danger'][$purchase->payment_status] ?? 'bg-secondary' }} fw-medium fs-10">
										{{ __('purchases.statuses.'.$purchase->payment_status) }}
									</span>
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
