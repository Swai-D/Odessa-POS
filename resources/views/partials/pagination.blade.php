@if ($paginator->hasPages() || $paginator->total() > 0)
	<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-3">
		<small class="text-muted">{{ __('app.showing', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}</small>
		@if ($paginator->hasPages())
			{{ $paginator->onEachSide(1)->links() }}
		@endif
	</div>
@endif
