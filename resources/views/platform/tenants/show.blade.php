@extends('layouts.app')

@section('title', __('platform.view').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $tenant->name }}</h4>
				<h6>{{ __('platform.details') }}</h6>
			</div>
		</div>
		<div class="page-btn d-flex gap-2">
			<a href="{{ route('platform.tenants.index') }}" class="btn btn-white"><i class="ti ti-arrow-left me-1"></i>{{ __('platform.back') }}</a>
			<a href="{{ route('platform.tenants.edit', $tenant) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>{{ __('platform.edit') }}</a>
		</div>
	</div>

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('platform.details') }}</h5></div>
		<div class="card-body">
			<div class="row g-3">
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.name') }}</div>
					<div class="fw-medium">{{ $tenant->name }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.slug') }}</div>
					<div class="fw-medium">{{ $tenant->slug }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.owner_name') }}</div>
					<div class="fw-medium">{{ $owner?->name ?? '—' }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.owner_email') }}</div>
					<div class="fw-medium">{{ $owner?->email ?? '—' }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.domain') }}</div>
					<div class="fw-medium">{{ $tenant->domain ?: '—' }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.plan') }}</div>
					<div class="fw-medium">{{ __('platform.plans.'.($tenant->plan ?? config('plans.default'))) }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.status') }}</div>
					<div class="fw-medium">{{ __('platform.statuses.'.$tenant->status) }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.deadline') }}</div>
					<div class="fw-medium">{{ ($tenant->paid_until ?? $tenant->trial_ends_at)?->format('Y-m-d') ?? '—' }}</div>
				</div>
				<div class="col-md-6">
					<div class="text-muted">{{ __('platform.registered') }}</div>
					<div class="fw-medium">{{ $tenant->created_at?->format('Y-m-d') ?? '—' }}</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection