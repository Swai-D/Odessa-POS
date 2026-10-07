@extends('layouts.app')

@section('title', __('users.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('users.title') }}</h4>
				<h6>{{ __('users.hint') }} · {{ $limit === null ? __('users.usage_unlimited', ['count' => $users->count()]) : __('users.usage', ['count' => $users->count(), 'limit' => $limit]) }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="javascript:void(0);" class="btn btn-primary js-create" data-bs-toggle="modal" data-bs-target="#user-modal"><i class="ti ti-circle-plus me-1"></i>{{ __('app.add') }}</a>
		</div>
	</div>

	@include('partials.flash')

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr>
							<th>{{ __('users.name') }}</th>
							<th>{{ __('users.email') }}</th>
							<th>{{ __('users.role') }}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@foreach ($users as $user)
							<tr>
								<td>{{ $user->name }}</td>
								<td>{{ $user->email }}</td>
								<td>{{ $user->roles->pluck('name')->join(', ') }}</td>
								<td class="action-table-data">
									<div class="edit-delete-action">
										<a class="me-2 p-2 js-edit" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#user-modal"
											data-action="{{ route('users.update', $user->getKey()) }}"
											data-record="{{ json_encode(['name' => $user->name, 'email' => $user->email, 'role' => $user->roles->first()?->name]) }}">
											<i data-feather="edit" class="feather-edit"></i>
										</a>
										@if (! $user->is(auth()->user()))
											<a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);"
												data-delete-action="{{ route('users.destroy', $user->getKey()) }}">
												<i data-feather="trash-2" class="feather-trash-2"></i>
											</a>
										@endif
									</div>
								</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="user-modal">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<div class="page-title"><h4>{{ __('users.title') }}</h4></div>
				<button type="button" class="close bg-danger text-white fs-16" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<form method="POST" action="{{ route('users.store') }}" id="user-form" data-store-action="{{ route('users.store') }}">
				@csrf
				<input type="hidden" name="_method" value="POST" id="user-method">
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">{{ __('users.name') }}<span class="text-danger ms-1">*</span></label>
						<input type="text" class="form-control" name="name" required>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('users.email') }}<span class="text-danger ms-1">*</span></label>
						<input type="email" class="form-control" name="email" required>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('users.role') }}<span class="text-danger ms-1">*</span></label>
						<select class="form-select" name="role" required>
							@foreach ($roles as $role)
								<option value="{{ $role }}">{{ $role }}</option>
							@endforeach
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('users.password') }}</label>
						<input type="password" class="form-control" name="password" minlength="8" autocomplete="new-password">
						<small class="text-muted js-password-hint" style="display:none;">{{ __('users.password_hint') }}</small>
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
@include('partials.delete-modal')
@endpush

@push('extra-js')
<script>
	(function () {
		var form = document.getElementById('user-form');
		var method = document.getElementById('user-method');
		var hint = form.querySelector('.js-password-hint');
		var password = form.querySelector('[name=password]');

		function fill(record) {
			['name', 'email', 'role'].forEach(function (key) {
				var input = form.querySelector('[name=' + key + ']');
				input.value = record && record[key] ? record[key] : (key === 'role' ? input.options[0].value : '');
			});
			password.value = '';
			password.required = !record;
			hint.style.display = record ? '' : 'none';
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
		fill(null);
	})();
</script>
@endpush
