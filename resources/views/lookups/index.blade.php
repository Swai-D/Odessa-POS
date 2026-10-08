@extends('layouts.app')

@section('title', __($title).' - Odessa POS')


@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __($title) }}</h4>
				<h6>{{ __('catalog.manage_lookup', ['name' => __($title)]) }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh" href="{{ route($route.'.index') }}"><i class="ti ti-refresh"></i></a></li>
			<li><a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"><i class="ti ti-chevron-up"></i></a></li>
		</ul>
		@if ($canManage)
			<div class="page-btn">
				<a href="javascript:void(0);" class="btn btn-primary js-create" data-bs-toggle="modal" data-bs-target="#lookup-modal"><i class="ti ti-circle-plus me-1"></i>{{ __('app.add') }}</a>
			</div>
		@endif
	</div>

	@include('partials.flash')

	<!-- /list -->
	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
			@include('partials.list-search')
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table">
					<thead class="thead-light">
						<tr>
							@foreach ($columns as $column)
								<th>{{ __($column['label']) }}</th>
							@endforeach
							<th>{{ __('catalog.fields.status') }}</th>
							@if ($canManage)
								<th class="no-sort"></th>
							@endif
						</tr>
					</thead>
					<tbody>
						@foreach ($records as $record)
							<tr>
								@foreach ($columns as $column)
									<td>{{ $column['value']($record) }}</td>
								@endforeach
								<td>
									<span class="badge table-badge {{ $record->is_active ? 'bg-success' : 'bg-danger' }} fw-medium fs-10">
										{{ $record->is_active ? __('app.yes') : __('app.no') }}
									</span>
								</td>
								@if ($canManage)
									<td class="action-table-data">
										<div class="edit-delete-action">
											<a class="me-2 p-2 js-edit" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#lookup-modal"
												data-action="{{ route($route.'.update', $record->getKey()) }}"
												data-record="{{ json_encode($record->only(array_column($fields, 'name'))) }}">
												<i data-feather="edit" class="feather-edit"></i>
											</a>
											<a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);"
												data-delete-action="{{ route($route.'.destroy', $record->getKey()) }}">
												<i data-feather="trash-2" class="feather-trash-2"></i>
											</a>
										</div>
									</td>
								@endif
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			@include('partials.pagination', ['paginator' => $records])
		</div>
	</div>
	<!-- /list -->
</div>
@endsection

@push('modals')
@if ($canManage)
<div class="modal fade" id="lookup-modal">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<div class="page-title"><h4 id="lookup-modal-title">{{ __($title) }}</h4></div>
				<button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="POST" action="{{ route($route.'.store') }}" id="lookup-form" data-store-action="{{ route($route.'.store') }}">
				@csrf
				<input type="hidden" name="_method" value="POST" id="lookup-method">
				<div class="modal-body">
					@foreach ($fields as $field)
						@if ($field['type'] === 'toggle')
							<div class="mb-3">
								<div class="status-toggle modal-status d-flex justify-content-between align-items-center">
									<span class="status-label">{{ __($field['label']) }}</span>
									<input type="hidden" name="{{ $field['name'] }}" value="0">
									<input type="checkbox" id="field-{{ $field['name'] }}" name="{{ $field['name'] }}" value="1" class="check" data-default="{{ $field['name'] === 'is_active' ? 1 : 0 }}">
									<label for="field-{{ $field['name'] }}" class="checktoggle"></label>
								</div>
							</div>
						@elseif ($field['type'] === 'select')
							<div class="mb-3">
								<label class="form-label">{{ __($field['label']) }}</label>
								<select class="form-select" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}">
									<option value="">{{ __('catalog.select') }}</option>
									@foreach ($field['options'] as $value => $label)
										<option value="{{ $value }}">{{ $label }}</option>
									@endforeach
								</select>
							</div>
						@else
							<div class="mb-3">
								<label class="form-label">{{ __($field['label']) }}@if (! empty($field['required']))<span class="text-danger ms-1">*</span>@endif</label>
								<input type="text" class="form-control" name="{{ $field['name'] }}" id="field-{{ $field['name'] }}" @required(! empty($field['required']))>
							</div>
						@endif
					@endforeach
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
		var form = document.getElementById('lookup-form');
		if (!form) { return; }
		var method = document.getElementById('lookup-method');

		function fill(record) {
			form.querySelectorAll('[name]').forEach(function (input) {
				if (input.name === '_token' || input.name === '_method' || input.type === 'hidden') { return; }
				var value = record ? record[input.name] : null;
				if (input.type === 'checkbox') {
					input.checked = record ? !!value : input.getAttribute('data-default') === '1';
				} else {
					input.value = value === null || value === undefined ? '' : value;
				}
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
