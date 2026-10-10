@extends('layouts.app')

@section('title', __('platform.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.title') }}</h4>
				<h6>{{ __('platform.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('platform.tenants.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('platform.new') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead>
						<tr>
							<th>{{ __('platform.name') }}</th>
							<th>{{ __('platform.slug') }}</th>
							<th>{{ __('platform.plan') }}</th>
							<th>{{ __('platform.status') }}</th>
							<th>{{ __('platform.deadline') }}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@forelse ($tenants as $tenant)
							<tr>
								<td>{{ $tenant->name }}</td>
								<td>{{ $tenant->slug }}</td>
								<td>{{ __('platform.plans.'.($tenant->plan ?? config('plans.default'))) }}</td>
								<td>{{ __('platform.statuses.'.$tenant->status) }}@if (($state = (new \App\Support\Subscription($tenant))->state()) !== 'active' && $tenant->status !== 'suspended') <span class="badge bg-warning ms-1">{{ __('subscription.states.'.$state) }}</span>@endif</td>
								<td>{{ ($tenant->paid_until ?? $tenant->trial_ends_at)?->format('Y-m-d') ?? '—' }}</td>
								<td class="text-end text-nowrap">
									<div class="action-icon d-inline-flex align-items-center">
										<a href="{{ route('platform.tenants.edit', $tenant) }}#renew" class="p-2 d-flex align-items-center border rounded me-2" title="{{ __('platform.renew') }}"><i class="ti ti-cash"></i></a>
										<a href="{{ route('platform.tenants.edit', $tenant) }}" class="p-2 d-flex align-items-center border rounded me-2" title="{{ __('platform.edit') }}"><i class="ti ti-edit"></i></a>
										<a href="javascript:void(0);" class="p-2 d-flex align-items-center border rounded text-danger" title="{{ __('platform.delete_shop') }}"
											data-bs-toggle="modal" data-bs-target="#delete-modal"
											data-delete-action="{{ route('platform.tenants.destroy', $tenant) }}"
											data-delete-message="{{ __('platform.confirm_delete_shop') }}">
											<i class="ti ti-trash"></i>
										</a>
									</div>
								</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">{{ __('platform.empty') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@push('modals')
@include('partials.delete-modal')
@endpush
