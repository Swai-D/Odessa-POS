@extends('layouts.app')

@section('title', __('platform.plans_title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex"><div class="page-title"><h4 class="fw-bold">{{ __('platform.plans_title') }}</h4><h6>{{ __('platform.plans_hint') }}</h6></div></div>
		<div class="page-btn"><a href="{{ route('platform.plans.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('platform.plan_new') }}</a></div>
	</div>
	@include('partials.flash')
	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead><tr><th>{{ __('platform.plan_name') }}</th><th>{{ __('platform.plan_code') }}</th><th>{{ __('platform.monthly_price') }}</th><th>{{ __('platform.annual_price') }}</th><th>{{ __('platform.plan_features') }}</th><th>{{ __('platform.status') }}</th><th></th></tr></thead>
					<tbody>
						@forelse ($plans as $plan)
							<tr>
								<td>{{ $plan->name }}</td>
								<td><code>{{ $plan->code }}</code></td>
								<td>{{ $plan->monthly_price === null ? __('platform.price_not_configured') : \App\Support\Money::format($plan->monthly_price, $currency) }}</td>
								<td>{{ $plan->annual_price === null ? __('platform.price_not_configured') : \App\Support\Money::format($plan->annual_price, $currency) }}</td>
								<td>{{ collect($plan->features)->map(fn ($feature) => $feature === '*' ? __('platform.all_features') : __('plans.features.'.$feature))->join(', ') ?: '—' }}</td>
								<td><span class="badge {{ $plan->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->is_active ? __('platform.statuses.active') : __('platform.inactive') }}</span></td>
								<td class="text-end text-nowrap">
									<div class="action-icon d-inline-flex align-items-center">
										<a href="{{ route('platform.plans.edit', $plan) }}" class="p-2 d-flex align-items-center border rounded me-2" title="{{ __('platform.edit') }}"><i class="ti ti-edit"></i></a>
										<a href="javascript:void(0);" class="p-2 d-flex align-items-center border rounded text-danger" title="{{ __('app.delete') }}"
											data-bs-toggle="modal" data-bs-target="#delete-modal"
											data-delete-action="{{ route('platform.plans.destroy', $plan) }}"
											data-delete-message="{{ __('platform.confirm_delete_plan') }}">
											<i class="ti ti-trash"></i>
										</a>
									</div>
								</td>
							</tr>
						@empty
							<tr><td colspan="7" class="text-center text-muted py-4">{{ __('platform.no_plans') }}</td></tr>
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