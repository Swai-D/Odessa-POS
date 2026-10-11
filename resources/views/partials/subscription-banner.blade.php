@php
	$subscription = \App\Support\Subscription::current();
	$state = $subscription->state();
@endphp
@if ($state === \App\Support\Subscription::READONLY)
	<div class="alert alert-danger mb-0 rounded-0 text-center">{{ __('subscription.readonly_banner') }}@can('manage-settings') <a href="{{ route('billing.index') }}" class="fw-semibold text-decoration-underline ms-1" data-billing-link>{{ __('billing.pay_now') }}</a>@endcan</div>
@elseif ($state === \App\Support\Subscription::GRACE)
	<div class="alert alert-warning mb-0 rounded-0 text-center">{{ __('subscription.grace_banner', ['date' => $subscription->graceEndsAt()?->format('Y-m-d')]) }}@can('manage-settings') <a href="{{ route('billing.index') }}" class="fw-semibold text-decoration-underline ms-1" data-billing-link>{{ __('billing.pay_now') }}</a>@endcan</div>
@elseif ($subscription->shouldWarn())
	<div class="alert alert-info mb-0 rounded-0 text-center">{{ __('subscription.renew_banner', ['date' => $subscription->deadline()?->format('Y-m-d'), 'days' => $subscription->daysLeft()]) }}@can('manage-settings') <a href="{{ route('billing.index') }}" class="fw-semibold text-decoration-underline ms-1" data-billing-link>{{ __('billing.pay_now') }}</a>@endcan</div>
@endif
