@php
	$subscription = \App\Support\Subscription::current();
	$state = $subscription->state();
@endphp
@if ($state === \App\Support\Subscription::READONLY)
	<div class="alert alert-danger mb-0 rounded-0 text-center">{{ __('subscription.readonly_banner') }}</div>
@elseif ($state === \App\Support\Subscription::GRACE)
	<div class="alert alert-warning mb-0 rounded-0 text-center">{{ __('subscription.grace_banner', ['date' => $subscription->graceEndsAt()?->format('Y-m-d')]) }}</div>
@elseif ($subscription->shouldWarn())
	<div class="alert alert-info mb-0 rounded-0 text-center">{{ __('subscription.renew_banner', ['date' => $subscription->deadline()?->format('Y-m-d'), 'days' => $subscription->daysLeft()]) }}</div>
@endif
