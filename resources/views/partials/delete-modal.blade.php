{{-- Delete confirmation modal (markup from the Dreams POS template). The form action is set by JS. --}}
<div class="modal fade" id="delete-modal">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="page-wrapper-new p-0">
				<div class="content p-5 px-3 text-center">
					<form method="POST" action="" id="delete-form">
						@csrf
						@method('DELETE')
						<span class="rounded-circle d-inline-flex p-2 bg-danger-transparent mb-2"><i class="ti ti-trash fs-24 text-danger"></i></span>
						<h4 class="fs-20 fw-bold mb-2 mt-1">{{ __('app.delete') }}</h4>
						<p class="mb-0 fs-16" id="delete-modal-message" data-default-message="{{ __('app.confirm_delete') }}">{{ __('app.confirm_delete') }}</p>
						<div class="modal-footer-btn mt-3 d-flex justify-content-center">
							<button type="button" class="btn me-2 btn-secondary fs-13 fw-medium p-2 px-3 shadow-none" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
							<button type="submit" class="btn btn-primary fs-13 fw-medium p-2 px-3">{{ __('app.yes_delete') }}</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-delete-action]');
		if (trigger) {
			document.getElementById('delete-form').setAttribute('action', trigger.getAttribute('data-delete-action'));
			var message = document.getElementById('delete-modal-message');
			message.textContent = trigger.getAttribute('data-delete-message') || message.getAttribute('data-default-message');
		}
	});
</script>
