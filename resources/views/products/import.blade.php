@extends('layouts.app')

@section('title', __('catalog.import.title').' - Odessa POS')

@section('content')
@php($result = session('import_result'))
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('catalog.import.title') }}</h4>
				<h6>{{ __('catalog.import.hint') }}</h6>
			</div>
		</div>
		<div class="page-btn">
			<a href="{{ route('products.index') }}" class="btn btn-secondary">{{ __('catalog.import.back') }}</a>
		</div>
	</div>

	@include('partials.flash')

	@if ($result)
		<div class="alert alert-success">
			<strong>{{ __('catalog.import.done') }}</strong>
			{{ __('catalog.import.summary', ['created' => $result['created'], 'updated' => $result['updated'], 'lookups' => $result['created_lookups']]) }}
			@foreach ($result['notes'] as $note)<div class="mt-1">{{ $note }}</div>@endforeach
		</div>
	@endif

	@if (session('import_problems'))
		<div class="alert alert-danger">
			<strong>{{ __('catalog.import.refused', ['count' => session('import_total')]) }}</strong>
			<ul class="mb-0 mt-2">
				@foreach (session('import_problems') as $problem)<li>{{ $problem }}</li>@endforeach
			</ul>
			@if (session('import_total') > count(session('import_problems')))
				<div class="mt-2">{{ __('catalog.import.and_more', ['count' => session('import_total') - count(session('import_problems'))]) }}</div>
			@endif
		</div>
	@endif

	@if ($errors->any())
		<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
	@endif

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('catalog.import.step1') }}</h5></div>
		<div class="card-body">
			<p>{{ __('catalog.import.step1_text') }}</p>
			<a href="{{ route('products.import.template') }}" class="btn btn-white"><i class="ti ti-download me-1"></i>{{ __('catalog.import.template') }}</a>
			<a href="{{ route('products.export') }}" class="btn btn-white ms-1"><i class="ti ti-file-export me-1"></i>{{ __('catalog.import.export') }}</a>
		</div>
	</div>

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('catalog.import.step2') }}</h5></div>
		<div class="card-body">
			<form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
				@csrf
				<div>
					<label class="form-label">{{ __('catalog.import.file') }}</label>
					<input type="file" name="file" accept=".csv,text/csv,text/plain" class="form-control" required>
				</div>
				<button type="submit" class="btn btn-primary">{{ __('catalog.import.upload') }}</button>
			</form>
			<small class="text-muted d-block mt-2">{{ __('catalog.import.rules', ['max' => $max]) }}</small>
		</div>
	</div>

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('catalog.import.columns') }}</h5></div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<tbody>
						@foreach ($columns as $column)
							<tr>
								<td class="fw-semibold"><code>{{ $column }}</code>@if (in_array($column, ['sku', 'name'], true))<span class="text-danger ms-1">*</span>@endif</td>
								<td>{{ __('catalog.import.col.'.$column) }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection
