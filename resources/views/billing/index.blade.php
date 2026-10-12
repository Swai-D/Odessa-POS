@extends('layouts.app')

@section('title', __('billing.title').' - Odessa POS')

@php
	use App\Support\Money;
	use App\Support\Plans;
	use App\Support\Subscription;

	$currency = (string) config('pos.default_currency');
	$price = fn (?int $amount) => $amount === null ? __('billing.not_set') : Money::format($amount, $currency);
	$badge = ['active' => 'bg-success', 'grace' => 'bg-warning', 'readonly' => 'bg-danger', 'suspended' => 'bg-dark'][$state] ?? 'bg-secondary';
	$limit = fn (string $name) => Plans::current()->limit($name) ?? __('billing.unlimited');
	$shop = $tenant->name;
	$hasContact = $contact['phone'] || $contact['email'] || $contact['whatsapp'];
@endphp

@section('content')
<div class="content">
	<div class="page-header">
		<div class="add-item d-flex">
			<div class="page-title">
				<h4 class="fw-bold">{{ __('billing.title') }}</h4>
				<h6>{{ __('billing.hint') }}</h6>
			</div>
		</div>
	</div>

	<div class="row">
		{{-- Where the subscription stands --}}
		<div class="col-lg-5 d-flex">
			<div class="card flex-fill" data-billing-status>
				<div class="card-body">
					<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
						<div>
							<div class="text-muted">{{ __('billing.your_plan') }}</div>
							<h3 class="mb-0">{{ $plan?->name ?? ucfirst((string) $tenant->plan) }}</h3>
						</div>
						<div class="text-end">
							<span class="badge {{ $badge }}" data-state="{{ $state }}">{{ __('billing.status.'.$state) }}</span>
							@if ($trial)
								<span class="badge bg-info ms-1">{{ __('billing.trial_badge') }}</span>
							@endif
						</div>
					</div>

					@if ($deadline)
						<div class="mb-3">
							<div class="text-muted">{{ $trial ? __('billing.trial_ends_on') : __('billing.ends_on') }}</div>
							<div class="fs-18 fw-semibold" data-deadline="{{ $deadline }}">{{ $deadline }}</div>
						</div>

						<div class="mb-1 text-muted">{{ __('billing.countdown') }}</div>
						<div class="d-flex gap-2 mb-3" data-countdown="{{ $deadline_at }}">
							@foreach (['days', 'hours', 'minutes', 'seconds'] as $unit)
								<div class="border rounded text-center px-3 py-2 flex-fill">
									<div class="fs-20 fw-bold" data-unit="{{ $unit }}">–</div>
									<div class="fs-12 text-muted">{{ __('billing.'.$unit) }}</div>
								</div>
							@endforeach
						</div>
						<div class="progress mb-3" style="height: 6px;">
							<div class="progress-bar {{ $state === Subscription::ACTIVE ? 'bg-primary' : 'bg-danger' }}" role="progressbar" style="width: {{ $elapsed_percent }}%" aria-valuenow="{{ $elapsed_percent }}" aria-valuemin="0" aria-valuemax="100"></div>
						</div>
					@else
						<p class="text-muted">{{ __('billing.no_deadline') }}</p>
					@endif

					@if ($trial)
						<div class="alert alert-info">{{ __('billing.trial_note', ['plan' => $trial_plan_name]) }}</div>
					@endif

					<div class="alert {{ $state === Subscription::ACTIVE ? 'alert-success' : ($state === Subscription::GRACE ? 'alert-warning' : 'alert-danger') }} mb-3" data-state-message>
						{{ __('billing.messages.'.$state, ['date' => $grace_ends]) }}
					</div>

					<div class="row g-2">
						<div class="col-6">
							<div class="text-muted fs-13">{{ __('billing.users_limit') }}</div>
							<div class="fw-semibold">{{ $limit('users') }}</div>
						</div>
						<div class="col-6">
							<div class="text-muted fs-13">{{ __('billing.warehouses_limit') }}</div>
							<div class="fw-semibold">{{ $limit('warehouses') }}</div>
						</div>
					</div>

					@if ($plan)
						<hr>
						<div class="text-muted mb-1">{{ __('billing.renewal_price') }}</div>
						<div>
							<span class="fw-semibold">{{ $price($plan->monthly_price) }}</span> {{ __('billing.per_month') }}<br>
							<span class="fw-semibold">{{ $price($plan->annual_price) }}</span> {{ __('billing.per_year') }}
						</div>
					@endif
				</div>
			</div>
		</div>

		{{-- How to pay --}}
		<div class="col-lg-7 d-flex">
			<div class="card flex-fill" data-billing-pay>
				<div class="card-header">
					<h5 class="mb-0">{{ __('billing.how_to_pay') }}</h5>
					<p class="text-muted mb-0">{{ __('billing.how_to_pay_hint') }}</p>
				</div>
				<div class="card-body">
					<div class="alert alert-light border">
						{{ __('billing.reference_is') }}: <strong>{{ $shop }}</strong>
					</div>

					@if ($methods || $bank)
						<ul class="nav nav-pills mb-3 gap-1" role="tablist">
							@foreach ($methods as $i => $method)
								<li class="nav-item"><button type="button" class="nav-link {{ $i === 0 ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#pay-{{ $method['key'] }}">{{ $method['label'] }}</button></li>
							@endforeach
							@if ($bank)
								<li class="nav-item"><button type="button" class="nav-link {{ $methods ? '' : 'active' }}" data-bs-toggle="pill" data-bs-target="#pay-bank">{{ __('billing.bank') }}</button></li>
							@endif
						</ul>
						<div class="tab-content">
							@foreach ($methods as $i => $method)
								<div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="pay-{{ $method['key'] }}" data-method="{{ $method['key'] }}">
									<div class="row g-3 mb-3">
										<div class="col-sm-6">
											<div class="text-muted fs-13">{{ __('billing.business_number') }}</div>
											<div class="fs-20 fw-bold">{{ $method['number'] }}</div>
										</div>
										<div class="col-sm-6">
											<div class="text-muted fs-13">{{ __('billing.account_name') }}</div>
											<div class="fw-semibold">{{ $account_name }}</div>
										</div>
									</div>
									<div class="fw-semibold mb-1">{{ __('billing.steps_title') }}</div>
									<ol class="mb-0">
										<li>{{ __('billing.steps.dial', ['ussd' => $method['ussd'], 'label' => $method['label']]) }}</li>
										<li>{{ __('billing.steps.choose') }}</li>
										<li>{{ __('billing.steps.number', ['number' => $method['number'], 'name' => $account_name]) }}</li>
										<li>{{ __('billing.steps.amount') }}</li>
										<li>{{ __('billing.steps.reference', ['shop' => $shop]) }}</li>
									</ol>
								</div>
							@endforeach
							@if ($bank)
								<div class="tab-pane fade {{ $methods ? '' : 'show active' }}" id="pay-bank" data-method="bank">
									<dl class="row mb-0">
										@if ($bank['name'] !== '')
											<dt class="col-sm-4">{{ __('billing.bank_name') }}</dt><dd class="col-sm-8">{{ $bank['name'] }}</dd>
										@endif
										<dt class="col-sm-4">{{ __('billing.bank_account') }}</dt><dd class="col-sm-8 fw-bold">{{ $bank['account'] }}</dd>
										<dt class="col-sm-4">{{ __('billing.account_name') }}</dt><dd class="col-sm-8">{{ $account_name }}</dd>
										@if ($bank['branch'])
											<dt class="col-sm-4">{{ __('billing.bank_branch') }}</dt><dd class="col-sm-8">{{ $bank['branch'] }}</dd>
										@endif
										<dt class="col-sm-4">{{ __('billing.reference_is') }}</dt><dd class="col-sm-8">{{ $shop }}</dd>
									</dl>
								</div>
							@endif
						</div>
					@else
						<p class="mb-0" data-no-methods>{{ __('billing.no_methods') }}</p>
					@endif

					<hr>
					<div class="fw-semibold mb-1">{{ __('billing.after_pay_title') }}</div>
					<p class="mb-3">{{ __('billing.after_pay') }}</p>

					@if ($hasContact)
						<div class="d-flex flex-wrap gap-2" data-contact>
							@if ($contact['phone'])
								<a class="btn btn-sm btn-outline-primary" href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}"><i class="ti ti-phone me-1"></i>{{ __('billing.call') }}: {{ $contact['phone'] }}</a>
							@endif
							@if ($contact['whatsapp'])
								<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $contact['whatsapp']) }}"><i class="ti ti-brand-whatsapp me-1"></i>{{ __('billing.whatsapp') }}</a>
							@endif
							@if ($contact['email'])
								<a class="btn btn-sm btn-outline-secondary" href="mailto:{{ $contact['email'] }}"><i class="ti ti-mail me-1"></i>{{ $contact['email'] }}</a>
							@endif
						</div>
					@endif
				</div>
			</div>
		</div>
	</div>

	{{-- Telling us a payment was made --}}
	<div class="card" data-billing-notice>
		<div class="card-header">
			<h5 class="mb-0">{{ __('billing.notice.title') }}</h5>
			<p class="text-muted mb-0">{{ __('billing.notice.hint') }}</p>
		</div>
		<div class="card-body">
			@include('partials.flash')
			<form method="POST" action="{{ route('billing.notices.store') }}" class="row g-3">
				@csrf
				<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::random(32) }}">
				<div class="col-md-4">
					<label class="form-label" for="notice-method">{{ __('billing.notice.method') }}</label>
					<select id="notice-method" name="method" class="form-select" required>
						@foreach ($notice_methods as $key)
							<option value="{{ $key }}" @selected(old('method') === $key)>{{ __('billing.notice.methods.'.$key) }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="notice-months">{{ __('billing.notice.period') }}</label>
					<select id="notice-months" name="months" class="form-select" required>
						<option value="1" @selected((int) old('months', 1) === 1)>{{ trans_choice('billing.months', 1, ['count' => 1]) }}</option>
						<option value="12" @selected((int) old('months') === 12)>{{ trans_choice('billing.months', 12, ['count' => 12]) }}</option>
					</select>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="notice-amount">{{ __('billing.notice.amount') }} ({{ $currency }})</label>
					<input id="notice-amount" type="number" name="amount" class="form-control" min="1" step="0.01" value="{{ old('amount') }}" required>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="notice-reference">{{ __('billing.notice.reference') }}</label>
					<input id="notice-reference" type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference') }}" required>
					<div class="form-text">{{ __('billing.notice.reference_hint') }}</div>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="notice-paid-on">{{ __('billing.notice.paid_on') }}</label>
					<input id="notice-paid-on" type="date" name="paid_on" class="form-control" max="{{ today()->format('Y-m-d') }}" value="{{ old('paid_on', today()->format('Y-m-d')) }}" required>
				</div>
				<div class="col-md-4">
					<label class="form-label" for="notice-note">{{ __('billing.notice.note') }}</label>
					<input id="notice-note" type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
				</div>
				<div class="col-12 d-flex align-items-center flex-wrap gap-3">
					<button type="submit" class="btn btn-primary">{{ __('billing.notice.send') }}</button>
					<span class="text-muted fs-13">{{ __('billing.notice.wait_note') }}</span>
				</div>
			</form>

			@if ($notices->isNotEmpty())
				<hr>
				<div class="fw-semibold mb-2">{{ __('billing.notice.sent_title') }}</div>
				<ul class="list-unstyled mb-0" data-notices>
					@foreach ($notices as $notice)
						<li class="d-flex flex-wrap justify-content-between gap-2 py-1 border-top" data-status="{{ $notice->status }}">
							<span>{{ $notice->created_at?->format('Y-m-d') }} · {{ Money::format($notice->amount, $notice->currency) }} · {{ __('billing.notice.methods.'.$notice->method) }} · {{ $notice->reference }}</span>
							<span class="badge {{ $notice->status === \App\Models\PaymentNotice::PENDING ? 'bg-warning' : 'bg-success' }}">{{ $notice->status === \App\Models\PaymentNotice::PENDING ? __('billing.notice.status_pending') : __('billing.notice.status_handled') }}</span>
						</li>
					@endforeach
				</ul>
			@endif
		</div>
	</div>

	{{-- Every plan and what it includes --}}
	<div class="card" data-billing-plans>
		<div class="card-header">
			<h5 class="mb-0">{{ __('billing.plans_title') }}</h5>
			<p class="text-muted mb-0">{{ __('billing.plans_hint') }}</p>
		</div>
		<div class="card-body">
			<div class="row g-3">
				@foreach ($plans as $p)
					@php $isCurrent = $plan && $plan->code === $p->code; @endphp
					<div class="col-md-4 d-flex">
						<div class="card flex-fill mb-0 {{ $isCurrent ? 'border-primary' : '' }}" data-plan="{{ $p->code }}" @if ($isCurrent) data-current="1" @endif>
							<div class="card-body">
								<div class="d-flex justify-content-between align-items-start mb-2">
									<h5 class="mb-0">{{ $p->name }}</h5>
									@if ($isCurrent)
										<span class="badge bg-primary">{{ __('billing.current') }}</span>
									@endif
								</div>
								<div class="mb-1"><span class="fs-20 fw-bold">{{ $price($p->monthly_price) }}</span> <span class="text-muted">{{ __('billing.per_month') }}</span></div>
								<div class="text-muted mb-3">{{ $price($p->annual_price) }} {{ __('billing.per_year') }}</div>
								<ul class="list-unstyled mb-3 fs-13">
									<li class="mb-1"><i class="ti ti-circle-check text-success me-1"></i>{{ __('billing.core') }}</li>
									@foreach ((array) config('plans.features') as $feature)
										@php $has = in_array('*', $p->features, true) || in_array($feature, $p->features, true); @endphp
										<li class="mb-1 {{ $has ? '' : 'text-muted' }}"><i class="ti {{ $has ? 'ti-circle-check text-success' : 'ti-circle-x text-muted' }} me-1"></i><span class="visually-hidden">{{ $has ? __('billing.included') : __('billing.not_included') }}: </span>{{ __('plans.features.'.$feature) }}</li>
									@endforeach
								</ul>
								<div class="text-muted fs-13">
									{{ __('billing.users_limit') }}: <strong>{{ $p->limits['users'] ?? __('billing.unlimited') }}</strong> ·
									{{ __('billing.warehouses_limit') }}: <strong>{{ $p->limits['warehouses'] ?? __('billing.unlimited') }}</strong>
								</div>
							</div>
						</div>
					</div>
				@endforeach
			</div>
		</div>
	</div>

	{{-- What this shop has paid --}}
	<div class="card" data-billing-history>
		<div class="card-header"><h5 class="mb-0">{{ __('billing.history') }}</h5></div>
		<div class="card-body p-0">
			@if ($payments->isEmpty())
				<p class="p-3 mb-0 text-muted">{{ __('billing.history_empty') }}</p>
			@else
				<div class="table-responsive">
					<table class="table mb-0">
						<thead class="thead-light">
							<tr>
								<th>{{ __('billing.h_date') }}</th>
								<th>{{ __('billing.h_plan') }}</th>
								<th>{{ __('billing.h_period') }}</th>
								<th class="text-end">{{ __('billing.h_amount') }}</th>
								<th>{{ __('billing.h_method') }}</th>
								<th>{{ __('billing.h_reference') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach ($payments as $payment)
								<tr>
									<td>{{ $payment->paid_on->format('Y-m-d') }}</td>
									<td>{{ $payment->plan_name ?? ucfirst((string) $payment->plan) }}</td>
									<td>{{ $payment->period_start->format('Y-m-d') }} → {{ $payment->period_end->format('Y-m-d') }} <span class="text-muted">({{ trans_choice('billing.months', $payment->months, ['count' => $payment->months]) }})</span></td>
									<td class="text-end">
										{{ Money::format($payment->amount, $payment->currency) }}
										@if ($payment->discount_amount > 0)
											<div class="text-muted fs-12">{{ __('billing.discount', ['amount' => Money::format($payment->discount_amount, $payment->currency)]) }}</div>
										@endif
									</td>
									<td>{{ \Illuminate\Support\Facades\Lang::has('pos.methods.'.$payment->method) ? __('pos.methods.'.$payment->method) : $payment->method }}</td>
									<td>{{ $payment->reference ?? '—' }}</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			@endif
		</div>
	</div>
</div>
@endsection

@push('extra-js')
	<script>
		(function () {
			var box = document.querySelector('[data-countdown]');
			if (!box) return;
			var end = new Date(box.getAttribute('data-countdown')).getTime();
			if (isNaN(end)) return;
			function set(unit, value) {
				var el = box.querySelector('[data-unit="' + unit + '"]');
				if (el) el.textContent = String(value);
			}
			function tick() {
				var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
				set('days', Math.floor(left / 86400));
				set('hours', Math.floor(left % 86400 / 3600));
				set('minutes', Math.floor(left % 3600 / 60));
				set('seconds', left % 60);
				if (left === 0) { clearInterval(timer); }
			}
			var timer = setInterval(tick, 1000);
			tick();
		})();
	</script>
@endpush
