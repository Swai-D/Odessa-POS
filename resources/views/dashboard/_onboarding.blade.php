<div class="card border-primary mb-4" data-onboarding>
	<div class="card-body">
		<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
			<div>
				<h5 class="mb-1">{{ __('onboarding.title') }}</h5>
				<p class="text-muted mb-0">{{ __('onboarding.progress', ['done' => $card['done'], 'total' => $card['total']]) }}</p>
			</div>
			<form method="POST" action="{{ route('onboarding.dismiss') }}">
				@csrf
				<button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('onboarding.dismiss') }}</button>
			</form>
		</div>
		<div class="progress mb-3" style="height: 6px;">
			<div class="progress-bar bg-primary" role="progressbar" style="width: {{ $card['percent'] }}%" aria-valuenow="{{ $card['percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
		</div>
		<ul class="list-unstyled mb-0">
			@foreach ($card['steps'] as $step)
				<li class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 border-top" data-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? 1 : 0 }}">
					<div>
						<i class="ti {{ $step['done'] ? 'ti-circle-check text-success' : 'ti-circle text-muted' }} me-2"></i>
						<span class="fw-medium {{ $step['done'] ? 'text-decoration-line-through text-muted' : '' }}">{{ __('onboarding.steps.'.$step['key'].'.title') }}</span>
						@unless ($step['done'])
							<div class="text-muted fs-13 ms-4">{{ __('onboarding.steps.'.$step['key'].'.hint') }}</div>
						@endunless
					</div>
					@unless ($step['done'])
						<div class="d-flex gap-2">
							<a href="{{ route($step['route']) }}" class="btn btn-sm btn-primary">{{ __('onboarding.go') }}</a>
							<a href="{{ route('help.show', ['category' => explode('/', $step['help'])[0], 'slug' => explode('/', $step['help'])[1]]) }}" class="btn btn-sm btn-outline-primary">{{ __('onboarding.guide') }}</a>
						</div>
					@endunless
				</li>
			@endforeach
		</ul>
	</div>
</div>
