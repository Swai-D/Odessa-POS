@extends('layouts.app')

@section('title', __('business_statement.title').' - Odessa POS')

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('business_statement.title') }}</h4>
				<h6>{{ __('business_statement.subtitle') }}</h6>
			</div>
		</div>
	</div>

	@include('partials.flash')

	@if ($canCreate)
		<div class="card">
			<div class="card-header"><h5 class="mb-0">{{ __('business_statement.create_heading') }}</h5></div>
			<div class="card-body">
				<form method="POST" action="{{ route('business-statements.store') }}" class="row g-3 align-items-end">
					@csrf
					<div class="col-md-4">
						<label class="form-label" for="st-months">{{ __('business_statement.period') }}</label>
						<select id="st-months" name="months" class="form-select" required>
							@foreach ($choices as $n)
								<option value="{{ $n }}" @selected((int) old('months', 6) === $n)>{{ __('business_statement.months', ['n' => $n]) }}</option>
							@endforeach
						</select>
						@error('months')<div class="text-danger small">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-4">
						<label class="form-label" for="st-locale">{{ __('business_statement.language') }}</label>
						<select id="st-locale" name="locale" class="form-select" required>
							<option value="en" @selected(old('locale', app()->getLocale()) === 'en')>English</option>
							<option value="sw" @selected(old('locale', app()->getLocale()) === 'sw')>Kiswahili</option>
						</select>
					</div>
					<div class="col-md-4"><button type="submit" class="btn btn-primary">{{ __('business_statement.create') }}</button></div>
				</form>
				<p class="text-muted small mt-3 mb-0">{{ __('business_statement.create_hint') }}</p>
			</div>
		</div>
	@endif

	<div class="card">
		<div class="card-header"><h5 class="mb-0">{{ __('business_statement.issued_heading') }}</h5></div>
		@if ($statements->isEmpty())
			<div class="card-body text-muted">{{ __('business_statement.none') }}</div>
		@else
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<thead>
						<tr>
							<th>{{ __('business_statement.doc.number') }}</th>
							<th>{{ __('business_statement.doc.issued') }}</th>
							<th>{{ __('business_statement.doc.period') }}</th>
							<th>{{ __('business_statement.doc.code') }}</th>
							<th>{{ __('business_statement.by') }}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						@foreach ($statements as $statement)
							<tr>
								<td><a href="{{ route('business-statements.show', $statement) }}">{{ $statement->number }}</a></td>
								<td>{{ $statement->generated_at->format('Y-m-d H:i') }}</td>
								<td>{{ $statement->period_from->format('Y-m-d') }} – {{ $statement->period_to->format('Y-m-d') }}</td>
								<td><code>{{ $statement->code }}</code></td>
								<td>{{ $statement->user?->name ?? '—' }}</td>
								<td class="text-end"><a href="{{ route('business-statements.pdf', $statement) }}">PDF</a></td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
			<div class="card-body">{{ $statements->links() }}</div>
		@endif
	</div>
</div>
@endsection
