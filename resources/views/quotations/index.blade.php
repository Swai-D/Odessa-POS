@extends('layouts.app')

@section('title', __('quotations.title').' - Odessa POS')

@php
	use App\Support\Money;
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('quotations.title') }}</h4>
				<h6>{{ __('quotations.subtitle') }}</h6>
			</div>
		</div>
		@if ($canCreate)
			<div class="page-btn">
				<a href="{{ route('quotations.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('quotations.new') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
			<div class="dropdown">
				<a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
					{{ $status ? __('quotations.statuses.'.$status) : __('quotations.all') }}
				</a>
				<ul class="dropdown-menu dropdown-menu-end p-3">
					<li><a href="{{ route('quotations.index') }}" class="dropdown-item rounded-1">{{ __('quotations.all') }}</a></li>
					@foreach (['draft', 'sent', 'accepted', 'declined', 'converted'] as $s)
						<li><a href="{{ route('quotations.index', ['status' => $s]) }}" class="dropdown-item rounded-1">{{ __('quotations.statuses.'.$s) }}</a></li>
					@endforeach
				</ul>
			</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>@include('partials.sort-header', ['label' => __('quotations.number'), 'key' => 'number'])</th>
							<th>@include('partials.sort-header', ['label' => __('quotations.date'), 'key' => 'date'])</th>
							<th>{{ __('quotations.customer') }}</th>
							<th>{{ __('quotations.valid_until') }}</th>
							<th>@include('partials.sort-header', ['label' => __('quotations.total'), 'key' => 'total'])</th>
							<th>{{ __('quotations.status') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($quotations as $quotation)
							<tr>
								<td><a href="{{ route('quotations.show', $quotation) }}">{{ $quotation->number }}</a></td>
								<td>{{ $quotation->quoted_at?->format('Y-m-d') }}</td>
								<td>{{ $quotation->displayName() ?: '—' }}</td>
								<td>{{ $quotation->valid_until?->format('Y-m-d') ?? '—' }}</td>
								<td>{{ Money::format($quotation->total, $quotation->currency) }}</td>
								<td>
									@if ($quotation->isExpired())
										<span class="badge table-badge bg-secondary fw-medium fs-10">{{ __('quotations.expired') }}</span>
									@else
										<span class="badge table-badge {{ ['draft' => 'bg-secondary', 'sent' => 'bg-info', 'accepted' => 'bg-success', 'declined' => 'bg-danger', 'converted' => 'bg-primary'][$quotation->status] ?? 'bg-secondary' }} fw-medium fs-10">{{ __('quotations.statuses.'.$quotation->status) }}</span>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">{{ __('quotations.empty') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
		<div class="card-footer">{{ $quotations->links() }}</div>
	</div>
</div>
@endsection
