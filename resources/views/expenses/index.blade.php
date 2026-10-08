@extends('layouts.app')

@section('title', __('expenses.title').' - Odessa POS')

@section('content')
@php
	$money = fn (int $amount) => \App\Support\Money::format($amount, $currency);
	$filters = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'category' => $category, 'q' => request('q')];
@endphp
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('expenses.title') }}</h4>
				<h6>{{ __('expenses.subtitle') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="CSV" href="{{ route('expenses.export', array_filter($filters)) }}"><i class="ti ti-download"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route('expenses.index') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
		@if ($canManage)
			<div class="page-btn">
				<a href="javascript:void(0);" class="btn btn-primary js-create" data-bs-toggle="modal" data-bs-target="#expense-modal"><i class="ti ti-circle-plus me-1"></i>{{ __('expenses.add') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<form method="GET" action="{{ route('expenses.index') }}" class="card">
		<div class="card-body d-flex flex-wrap align-items-end gap-3">
			<div>
				<label class="form-label">{{ __('reports.from') }}</label>
				<input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label">{{ __('reports.to') }}</label>
				<input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
			</div>
			<div>
				<label class="form-label">{{ __('expenses.category') }}</label>
				<select name="category" class="form-select">
					<option value="">{{ __('expenses.all_categories') }}</option>
					@foreach ($categories as $cat)
						<option value="{{ $cat->id }}" @selected($category === $cat->id)>{{ $cat->name }}</option>
					@endforeach
				</select>
			</div>
			@if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
			<button type="submit" class="btn btn-primary">{{ __('reports.apply') }}</button>
		</div>
	</form>

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
			<div class="fw-bold">{{ __('expenses.total_in_period') }}: {{ $money($total) }}</div>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							<th>@include('partials.sort-header', ['label' => __('expenses.date'), 'key' => 'date'])</th>
							<th>{{ __('expenses.category') }}</th>
							<th>{{ __('expenses.method') }}</th>
							<th>{{ __('expenses.reference') }}</th>
							<th>{{ __('expenses.note') }}</th>
							<th>{{ __('expenses.recorded_by') }}</th>
							<th class="text-end">@include('partials.sort-header', ['label' => __('expenses.amount'), 'key' => 'amount'])</th>
							@if ($canManage)<th class="no-sort"></th>@endif
						</tr>
					</thead>
					<tbody>
						@forelse ($expenses as $expense)
							<tr>
								<td>{{ $expense->spent_on->format('Y-m-d') }}</td>
								<td>{{ $expense->category?->name }}</td>
								<td>{{ __('pos.methods.'.$expense->method) }}</td>
								<td>{{ $expense->reference ?? '—' }}</td>
								<td>{{ $expense->note ?? '—' }}</td>
								<td>{{ $expense->user?->name ?? '—' }}</td>
								<td class="text-end">{{ $money($expense->amount) }}</td>
								@if ($canManage)
									<td class="action-table-data">
										<div class="edit-delete-action">
											<a class="me-2 p-2 js-edit" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#expense-modal"
												data-action="{{ route('expenses.update', $expense->getKey()) }}"
												data-record="{{ json_encode([
													'expense_category_id' => $expense->expense_category_id,
													'amount' => \App\Support\Money::toMajor($expense->amount),
													'method' => $expense->method,
													'spent_on' => $expense->spent_on->format('Y-m-d'),
													'reference' => $expense->reference,
													'note' => $expense->note,
												]) }}">
												<i data-feather="edit" class="feather-edit"></i>
											</a>
											<a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);"
												data-delete-action="{{ route('expenses.destroy', $expense->getKey()) }}">
												<i data-feather="trash-2" class="feather-trash-2"></i>
											</a>
										</div>
									</td>
								@endif
							</tr>
						@empty
							<tr><td colspan="8" class="text-center text-muted py-4">{{ __('expenses.none') }}</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $expenses])
		</div>
	</div>
</div>
@endsection

@push('modals')
@if ($canManage)
<div class="modal fade" id="expense-modal">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<div class="page-title"><h4>{{ __('expenses.title') }}</h4></div>
				<button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="POST" action="{{ route('expenses.store') }}" id="expense-form" data-store-action="{{ route('expenses.store') }}">
				@csrf
				<input type="hidden" name="_method" value="POST" id="expense-method">
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.category') }}<span class="text-danger ms-1">*</span></label>
						<select class="form-select" name="expense_category_id" required>
							<option value="">{{ __('catalog.select') }}</option>
							@foreach ($categories->where('is_active', true) as $cat)
								<option value="{{ $cat->id }}">{{ $cat->name }}</option>
							@endforeach
						</select>
						@can('create', \App\Domain\Finance\Models\ExpenseCategory::class)
							<small><a href="{{ route('expense-categories.index') }}">{{ __('expenses.manage_categories') }}</a></small>
						@endcan
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.amount') }} ({{ $currency }})<span class="text-danger ms-1">*</span></label>
						<input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.date') }}<span class="text-danger ms-1">*</span></label>
						<input type="date" class="form-control" name="spent_on" max="{{ now()->format('Y-m-d') }}" data-default="{{ now()->format('Y-m-d') }}" required>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.method') }}</label>
						<select class="form-select" name="method" data-default="cash">
							@foreach ($methods as $method)
								<option value="{{ $method }}">{{ __('pos.methods.'.$method) }}</option>
							@endforeach
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.reference') }}</label>
						<input type="text" class="form-control" name="reference" maxlength="100">
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('expenses.note') }}</label>
						<input type="text" class="form-control" name="note" maxlength="500">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
					<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
				</div>
			</form>
		</div>
	</div>
</div>
@endif
@include('partials.delete-modal')
@endpush

@push('extra-js')
<script>
	(function () {
		var form = document.getElementById('expense-form');
		if (!form) { return; }
		var method = document.getElementById('expense-method');

		function fill(record) {
			form.querySelectorAll('[name]').forEach(function (input) {
				if (input.name === '_token' || input.name === '_method') { return; }
				var value = record ? record[input.name] : input.getAttribute('data-default');
				input.value = value === null || value === undefined ? '' : value;
			});
		}

		document.addEventListener('click', function (event) {
			var edit = event.target.closest('.js-edit');
			var create = event.target.closest('.js-create');
			if (edit) {
				form.setAttribute('action', edit.getAttribute('data-action'));
				method.value = 'PUT';
				fill(JSON.parse(edit.getAttribute('data-record')));
			} else if (create) {
				form.setAttribute('action', form.getAttribute('data-store-action'));
				method.value = 'POST';
				fill(null);
			}
		});
	})();
</script>
@endpush
