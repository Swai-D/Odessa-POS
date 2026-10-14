@extends('layouts.app')

@section('title', $statement->number.' - Odessa POS')

@section('content')
<style>

	.stmt { font-size: 12px; color: #212b36; }
	.stmt .r { text-align: right; }
	.stmt .strong { font-weight: bold; }
	.stmt h3 { font-size: 14px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
	.stmt table { width: 100%; border-collapse: collapse; }
	.stmt-head td { vertical-align: top; padding: 0; }
	.stmt-title { font-size: 22px; font-weight: bold; }
	.stmt-shop { font-size: 16px; margin-top: 2px; }
	.stmt-grid td { padding: 3px 4px; border-bottom: 1px solid #eee; }
	.stmt-table th, .stmt-table td { padding: 4px 3px; font-size: 10.5px; border-bottom: 1px solid #eee; }
	.stmt-table th { border-bottom: 1px solid #999; text-align: left; }
	.stmt-table th.r { text-align: right; }
	.stmt-table tr.total td { font-weight: bold; border-top: 1px solid #999; }
	.stmt-verify { margin-top: 20px; padding: 8px 10px; border: 1px solid #999; }
	.stmt-code { font-size: 16px; font-weight: bold; letter-spacing: 1px; margin-top: 4px; }
	.stmt-note { margin-top: 14px; color: #666; font-size: 10.5px; }

	@media print { .no-print, .header, .sidebar, .page-header { display: none !important; } .page-wrapper { margin: 0 !important; padding: 0 !important; } .card { border: 0 !important; box-shadow: none !important; } }
</style>
<div class="content">
	<div class="page-header no-print">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ $statement->number }}</h4>
				<h6><a href="{{ route('business-statements.index') }}">{{ __('business_statement.title') }}</a></h6>
			</div>
		</div>
		<div class="page-btn d-flex gap-2">
			<a href="{{ route('business-statements.pdf', $statement) }}" class="btn btn-primary">{{ __('business_statement.download_pdf') }}</a>
			<button type="button" class="btn btn-outline-secondary" onclick="window.print()">{{ __('business_statement.print') }}</button>
		</div>
	</div>

	@include('partials.flash')

	<div class="card"><div class="card-body">
		@include('business-statements._document')
	</div></div>
</div>
@endsection
