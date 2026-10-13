@extends('layouts.app')

@section('title', $stocktake->number.' - Odessa POS')

@section('content')
@php
	$qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
	$editable = $stocktake->isOpen() && $canManage;
@endphp
<div class="content" data-stocktake-show data-status="{{ $stocktake->status }}">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $stocktake->number }} · {{ $stocktake->warehouse?->name }}</h4>
				<h6>{{ __('stocktake.statuses.'.$stocktake->status) }} · {{ $stocktake->started_at->format('Y-m-d H:i') }} · {{ $stocktake->user?->name ?? '—' }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a href="{{ route('stocktakes.index') }}" title="{{ __('stocktake.back') }}"><i class="ti ti-arrow-left"></i></a></li>
		</ul>
	</div>

	@include('partials.flash')

	<div class="row">
		<div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">{{ __('stocktake.lines') }}</div><div class="fs-20 fw-bold">{{ $totals['lines'] }}</div></div></div></div>
		<div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">{{ __('stocktake.counted') }}</div><div class="fs-20 fw-bold" data-counted>{{ $totals['counted'] }}</div></div></div></div>
		<div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">{{ __('stocktake.differences') }}</div><div class="fs-20 fw-bold" data-different>{{ $totals['different'] }}</div></div></div></div>
	</div>

	@if ($stocktake->note)
		<p class="text-muted">{{ $stocktake->note }}</p>
	@endif

	@if ($editable)
		<div class="alert alert-info">{{ __('stocktake.how_to') }}</div>
	@elseif ($stocktake->status === 'completed')
		<div class="d-flex gap-2 mb-3">
			<a href="{{ route('stocktakes.show', $stocktake) }}" class="btn btn-sm {{ $differencesOnly ? 'btn-outline-primary' : 'btn-primary' }}">{{ __('stocktake.all_lines') }}</a>
			<a href="{{ route('stocktakes.show', [$stocktake, 'differences' => 1]) }}" class="btn btn-sm {{ $differencesOnly ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('stocktake.only_differences') }}</a>
		</div>
	@endif

	@if ($editable)
		<form method="POST" action="{{ route('stocktakes.update', $stocktake) }}" id="counts-form">
			@csrf
			@method('PUT')
		</form>
	@endif

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr>
							<th>{{ __('stocktake.sku') }}</th>
							<th>{{ __('stocktake.product') }}</th>
							<th class="text-end">{{ __('stocktake.expected') }}</th>
							<th class="text-end" style="width: 160px;">{{ __('stocktake.counted_qty') }}</th>
							<th class="text-end">{{ __('stocktake.difference') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse ($items as $item)
							@php $difference = $item->difference(); @endphp
							<tr data-item="{{ $item->sku }}">
								<td>{{ $item->sku }}</td>
								<td>{{ $item->product_name }}</td>
								<td class="text-end">{{ $qty($item->expected) }}</td>
								<td class="text-end">
									@if ($editable)
										<input type="number" form="counts-form" name="counts[{{ $item->id }}]" value="{{ old('counts.'.$item->id, $item->counted === null ? '' : $qty($item->counted)) }}" min="0" step="any" class="form-control form-control-sm text-end">
									@else
										{{ $item->counted === null ? '—' : $qty($item->counted) }}
									@endif
								</td>
								<td class="text-end {{ $difference === null || $difference == 0 ? 'text-muted' : ($difference > 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold') }}">
									{{ $difference === null ? '—' : ($difference > 0 ? '+' : '').$qty($difference) }}
								</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-center text-muted py-4">{{ __('stocktake.no_lines') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $items])
		</div>
		@if ($editable)
			<div class="card-footer d-flex flex-wrap gap-2 justify-content-between">
				<div class="d-flex gap-2">
					<button type="submit" form="counts-form" class="btn btn-primary">{{ __('stocktake.save') }}</button>
					<button type="submit" form="counts-form" name="complete" value="1" class="btn btn-success" data-complete onclick="return confirm(@js(__('stocktake.confirm_complete')));">{{ __('stocktake.complete') }}</button>
				</div>
				<form method="POST" action="{{ route('stocktakes.cancel', $stocktake) }}" onsubmit="return confirm(@js(__('stocktake.confirm_cancel')));">
					@csrf
					<button type="submit" class="btn btn-outline-danger">{{ __('stocktake.cancel') }}</button>
				</form>
			</div>
		@endif
	</div>
</div>
@endsection
