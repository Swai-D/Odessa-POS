@extends('layouts.app')

@section('title', __('platform.notices.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('platform.notices.title') }}</h4>
				<h6>{{ __('platform.notices.hint') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a href="{{ route('platform.notices.index', $showAll ? [] : ['status' => 'all']) }}" class="btn btn-sm btn-white">{{ $showAll ? __('platform.notices.only_pending') : __('platform.notices.show_all') }}</a></li>
		</ul>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header"><span class="fw-semibold">{{ trans_choice('platform.notices.pending_count', $pendingCount, ['count' => $pendingCount]) }}</span></div>
		<div class="card-body p-0">
			@if ($notices->isEmpty())
				<p class="p-3 mb-0 text-muted">{{ __('platform.notices.empty') }}</p>
			@else
				<div class="table-responsive">
					<table class="table mb-0">
						<thead class="thead-light">
							<tr>
								<th>{{ __('platform.notices.sent') }}</th>
								<th>{{ __('platform.notices.shop') }}</th>
								<th>{{ __('platform.notices.by') }}</th>
								<th class="text-end">{{ __('platform.notices.amount') }}</th>
								<th>{{ __('platform.notices.method') }}</th>
								<th>{{ __('platform.notices.reference') }}</th>
								<th>{{ __('platform.notices.paid_on') }}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							@foreach ($notices as $notice)
								<tr data-notice="{{ $notice->id }}" data-status="{{ $notice->status }}">
									<td>{{ $notice->created_at?->format('Y-m-d H:i') }}</td>
									<td><a href="{{ route('platform.tenants.show', $notice->tenant_id) }}">{{ $notice->tenant?->name }}</a></td>
									<td>{{ $notice->user?->name ?? '—' }}</td>
									<td class="text-end">
										{{ \App\Support\Money::format($notice->amount, $notice->currency) }}
										<div class="text-muted fs-12">{{ trans_choice('billing.months', $notice->months, ['count' => $notice->months]) }}</div>
									</td>
									<td>{{ __('billing.notice.methods.'.$notice->method) }}</td>
									<td>
										{{ $notice->reference }}
										@if ($notice->note)
											<div class="text-muted fs-12">{{ $notice->note }}</div>
										@endif
									</td>
									<td>{{ $notice->paid_on->format('Y-m-d') }}</td>
									<td class="text-end">
										@if ($notice->status === \App\Models\PaymentNotice::PENDING)
											<a href="{{ route('platform.tenants.show', $notice->tenant_id) }}" class="btn btn-sm btn-primary">{{ __('platform.notices.open_shop') }}</a>
											<form method="POST" action="{{ route('platform.notices.handle', $notice) }}" class="d-inline">
												@csrf
												<button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('platform.notices.mark_handled') }}</button>
											</form>
										@else
											<span class="badge bg-success">{{ __('platform.notices.handled') }}</span>
										@endif
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="p-3">{{ $notices->links() }}</div>
			@endif
		</div>
	</div>
</div>
@endsection
