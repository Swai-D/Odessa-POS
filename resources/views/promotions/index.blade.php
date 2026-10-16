@extends('layouts.app')

@section('title', __('promotions.title').' - Odessa POS')

@php
	use App\Support\Money;
	$currency = (string) (new \App\Support\TenantSettings)->get('currency', config('pos.default_currency'));
	$summary = function ($p) use ($currency) {
		$off = fn () => ($p->type === 'bulk' ? $p->value_kind : ($p->type === 'fixed' ? 'fixed' : 'percent')) === 'fixed'
			? __('promotions.summary.fixed', ['amount' => Money::format($p->value, $currency)])
			: __('promotions.summary.percent', ['value' => rtrim(rtrim(number_format($p->value / 100, 2, '.', ''), '0'), '.')]);
		return match ($p->type) {
			'bogo' => __('promotions.summary.bogo', ['buy' => $p->buy_qty, 'get' => $p->get_qty]),
			'bulk' => __('promotions.summary.bulk', ['qty' => rtrim(rtrim(number_format((float) $p->min_qty, 3, '.', ''), '0'), '.'), 'off' => $off()]),
			default => $off(),
		};
	};
	$status = function ($p) use ($now, $engine) {
		$run = $p->toEngine();
		if (! $p->is_active) { return ['off', 'bg-secondary']; }
		if ($p->exhausted()) { return ['used_up', 'bg-secondary']; }
		if ($p->ends_on && $now['date'] > $p->ends_on->format('Y-m-d')) { return ['ended', 'bg-secondary']; }
		if ($p->starts_on && $now['date'] < $p->starts_on->format('Y-m-d')) { return ['scheduled', 'bg-info']; }
		return $engine->running($run, $now) ? ['running', 'bg-success'] : ['waiting', 'bg-warning'];
	};
	$days = fn ($p) => $p->days ? collect(explode(',', $p->days))->map(fn ($d) => __('promotions.weekdays.'.$d))->implode(', ') : null;
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('promotions.title') }}</h4>
				<h6>{{ __('promotions.subtitle') }}</h6>
			</div>
		</div>
		@if ($canManage)
			<div class="page-btn">
				<a href="{{ route('promotions.create') }}" class="btn btn-primary"><i class="ti ti-circle-plus me-1"></i>{{ __('promotions.new') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>@include('partials.sort-header', ['label' => __('promotions.name'), 'key' => 'name'])</th>
							<th>{{ __('promotions.offer') }}</th>
							<th>{{ __('promotions.applies_to') }}</th>
							<th>{{ __('promotions.when') }}</th>
							<th>{{ __('promotions.status') }}</th>
							<th class="no-sort"></th>
						</tr>
					</thead>
					<tbody>
						@forelse ($promotions as $promotion)
							@php
								[$state, $badge] = $status($promotion);
							@endphp
							<tr>
								<td>
									{{ $promotion->name }}
									@if ($promotion->isCoupon())
										<div><span class="badge bg-light text-dark border fs-10">{{ __('promotions.coupon') }}: {{ $promotion->code }}</span></div>
										<div class="small text-muted">{{ __('promotions.uses', ['used' => $promotion->uses_count, 'max' => $promotion->max_uses ?? '∞']) }}</div>
									@endif
								</td>
								<td>{{ $summary($promotion) }}</td>
								<td>{{ $promotion->scope === 'all' ? __('promotions.scope_all') : __('promotions.scope_count.'.$promotion->scope, ['count' => count($promotion->scope_ids ?? [])]) }}</td>
								<td class="small">
									{{ $promotion->starts_on || $promotion->ends_on ? ($promotion->starts_on?->format('Y-m-d') ?? '…').' → '.($promotion->ends_on?->format('Y-m-d') ?? '…') : __('promotions.always') }}
									@if ($days($promotion))<div>{{ $days($promotion) }}</div>@endif
									@if ($promotion->time_from || $promotion->time_to)<div>{{ $promotion->time_from ?? '00:00' }}–{{ $promotion->time_to ?? '24:00' }}</div>@endif
								</td>
								<td><span class="badge table-badge {{ $badge }} fw-medium fs-10">{{ __('promotions.states.'.$state) }}</span></td>
								<td class="action-table-data">
									@if ($canManage)
										<div class="edit-delete-action">
											<form method="POST" action="{{ route('promotions.toggle', $promotion) }}" class="d-inline">
												@csrf
												<button type="submit" class="btn btn-sm btn-white me-1">{{ $promotion->is_active ? __('promotions.turn_off') : __('promotions.turn_on') }}</button>
											</form>
											<a class="me-2 p-2" href="{{ route('promotions.edit', $promotion) }}"><i data-feather="edit" class="feather-edit"></i></a>
											<a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#delete-modal" data-delete-action="{{ route('promotions.destroy', $promotion) }}"><i data-feather="trash-2" class="feather-trash-2"></i></a>
										</div>
									@endif
								</td>
							</tr>
						@empty
							<tr><td colspan="6" class="text-center text-muted py-4">{{ __('promotions.empty') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
		<div class="card-footer">{{ $promotions->links() }}</div>
	</div>
</div>
@include('partials.delete-modal')
@endsection
