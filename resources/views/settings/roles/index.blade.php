@extends('layouts.app')

@section('title', __('roles.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('roles.title') }}</h4>
				<h6>{{ __('roles.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="javascript:void(0);" class="btn btn-primary js-create" data-bs-toggle="modal" data-bs-target="#role-modal"><i class="ti ti-circle-plus me-1"></i>{{ __('app.add') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr>
							<th>{{ __('roles.name') }}</th>
							<th>{{ __('roles.type') }}</th>
							<th>{{ __('roles.users') }}</th>
							<th>{{ __('roles.permissions') }}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@foreach ($roles as $role)
							@php $isDefault = in_array($role->name, $system, true); @endphp
							<tr data-role="{{ $role->name }}">
								<td>{{ $role->name }}</td>
								<td><span class="badge {{ $isDefault ? 'badge-soft-secondary' : 'badge-soft-success' }}">{{ $isDefault ? __('roles.default') : __('roles.custom') }}</span></td>
								<td>{{ $role->users_count }}</td>
								<td>{{ __('roles.permissions_count', ['count' => $role->permissions->count()]) }}</td>
								<td class="action-table-data">
									@unless ($isDefault)
										<div class="edit-delete-action">
											<a class="me-2 p-2 js-edit" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#role-modal"
												data-action="{{ route('roles.update', $role->getKey()) }}"
												data-record="{{ json_encode(['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->values()]) }}">
												<i data-feather="edit" class="feather-edit"></i>
											</a>
											<a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);"
												data-delete-action="{{ route('roles.destroy', $role->getKey()) }}">
												<i data-feather="trash-2" class="feather-trash-2"></i>
											</a>
										</div>
									@endunless
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
		<div class="card-footer text-muted fs-13">{{ __('roles.default_locked') }}</div>
	</div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="role-modal">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<div class="page-title"><h4>{{ __('roles.title') }}</h4></div>
				<button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form method="POST" action="{{ route('roles.store') }}" id="role-form" data-store-action="{{ route('roles.store') }}">
				@csrf
				<input type="hidden" name="_method" value="POST" id="role-method">
				<div class="modal-body">
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label">{{ __('roles.name') }}<span class="text-danger ms-1">*</span></label>
							<input type="text" class="form-control" name="name" maxlength="60" required>
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">{{ __('roles.copy_from') }}</label>
							<select class="form-select" id="role-copy">
								<option value="">{{ __('roles.copy_none') }}</option>
								@foreach ($roles as $role)
									<option value="{{ $role->name }}" data-permissions="{{ json_encode($role->permissions->pluck('name')->values()) }}">{{ $role->name }}</option>
								@endforeach
							</select>
						</div>
					</div>
					<p class="fw-semibold mb-2">{{ __('roles.pick_permissions') }}</p>
					<div class="row">
						@foreach ($groups as $area => $permissions)
							<div class="col-md-6 mb-3">
								<div class="border rounded p-2 h-100">
									<div class="fw-semibold mb-1">{{ __('roles.areas.'.$area) }}</div>
									@foreach ($permissions as $permission)
										<div class="form-check">
											<input class="form-check-input js-permission" type="checkbox" name="permissions[]" value="{{ $permission['name'] }}" id="perm-{{ str_replace('.', '-', $permission['name']) }}">
											<label class="form-check-label" for="perm-{{ str_replace('.', '-', $permission['name']) }}">{{ __('roles.actions.'.$permission['action']) }}</label>
										</div>
									@endforeach
								</div>
							</div>
						@endforeach
					</div>
					@error('permissions')<div class="text-danger fs-13">{{ $message }}</div>@enderror
				</div>
				<div class="modal-footer">
					<button type="button" class="btn me-2 btn-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
					<button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
				</div>
			</form>
		</div>
	</div>
</div>
@include('partials.delete-modal')
@endpush

@push('extra-js')
<script>
	(function () {
		var form = document.getElementById('role-form');
		var method = document.getElementById('role-method');
		var copy = document.getElementById('role-copy');
		var boxes = form.querySelectorAll('.js-permission');

		function check(names) {
			boxes.forEach(function (box) { box.checked = names.indexOf(box.value) !== -1; });
		}

		function fill(record) {
			form.querySelector('[name=name]').value = record ? record.name : '';
			copy.value = '';
			check(record ? record.permissions : []);
		}

		copy.addEventListener('change', function () {
			var option = copy.options[copy.selectedIndex];
			if (option.value) { check(JSON.parse(option.getAttribute('data-permissions'))); }
		});

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
