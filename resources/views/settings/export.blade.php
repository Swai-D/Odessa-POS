@extends('layouts.app')

@section('title', __('export.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('export.title') }}</h4>
				<h6>{{ __('export.hint') }}</h6>
			</div>
		</div>
		<ul class="table-top-head">
			<li><a href="{{ route('settings.index') }}" title="{{ __('export.back') }}"><i class="ti ti-arrow-left"></i></a></li>
		</ul>
	</div>

	<div class="card">
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table mb-0">
					<thead class="thead-light">
						<tr><th>{{ __('export.data') }}</th><th>{{ __('export.contains') }}</th><th></th></tr>
					</thead>
					<tbody>
						@foreach ($keys as $key)
							<tr data-dataset="{{ $key }}">
								<td class="fw-semibold">{{ __('export.sets.'.$key.'.name') }}</td>
								<td class="text-muted">{{ __('export.sets.'.$key.'.about') }}</td>
								<td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('settings.export.download', $key) }}"><i class="ti ti-download me-1"></i>CSV</a></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
		<div class="card-footer text-muted">{{ __('export.note') }}</div>
	</div>
</div>
@endsection
