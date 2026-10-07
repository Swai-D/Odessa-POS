<!-- Header -->
		<div class="header">
			<div class="main-header">
				<!-- Logo -->
				<div class="header-left active">
					<a href="{{ route('dashboard') }}" class="logo logo-normal">
						<img src="{{ asset('assets/img/logo.svg') }}" alt="Img">
					</a>
					<a href="{{ route('dashboard') }}" class="logo logo-white">
						<img src="{{ asset('assets/img/logo-white.svg') }}" alt="Img">
					</a>
					<a href="{{ route('dashboard') }}" class="logo-small">
						<img src="{{ asset('assets/img/logo-small.png') }}" alt="Img">
					</a>
					<a href="{{ route('dashboard') }}" class="logo-small-white">
						<img src="{{ asset('assets/img/logo-small-white.png') }}" alt="Img">
					</a>
				</div>
				<!-- /Logo -->
				<a id="mobile_btn" class="mobile_btn" href="{{ route('dashboard') }}#sidebar">
					<span class="bar-icon">
						<span></span>
						<span></span>
						<span></span>
					</span>
				</a>

				<!-- Header Menu -->
				<ul class="nav user-menu">
					@php
						$navUser = auth()->user();
						$navPlan = \App\Support\Plans::current();
						$hasShop = app(\App\Support\Tenancy\TenantContext::class)->get() !== null;
						// Quick "Add New" links: shown only when the user holds the permission and the plan has the feature.
						$quick = collect([
							['app.menu.products', 'ti ti-square-plus', 'products.create', 'products.manage', null],
							['app.menu.categories', 'ti ti-brand-codepen', 'categories.index', 'categories.manage', null],
							['app.menu.customers', 'ti ti-users', 'customers.index', 'customers.manage', null],
							['app.menu.stock_adjustments', 'ti ti-stairs-up', 'stock-adjustments.index', 'inventory.manage', null],
							['app.menu.purchases', 'ti ti-truck-delivery', 'purchases.create', 'purchases.manage', 'purchasing'],
							['app.menu.suppliers', 'ti ti-building-store', 'suppliers.index', 'suppliers.manage', 'purchasing'],
						])->filter(fn (array $q): bool => $hasShop && $navUser !== null
							&& ($navUser->is_super_admin || $navUser->can($q[3]))
							&& ($q[4] === null || $navPlan->allows($q[4])));
					@endphp

					@if ($quick->isNotEmpty())
						<li class="nav-item dropdown link-nav">
							<a href="javascript:void(0);" class="btn btn-primary btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
								<i class="ti ti-circle-plus me-1"></i>{{ __('app.add') }}
							</a>
							<div class="dropdown-menu dropdown-xl dropdown-menu-center">
								<div class="row g-2">
									@foreach ($quick as [$label, $icon, $route])
										<div class="col-md-2">
											<a href="{{ route($route) }}" class="link-item">
												<span class="link-icon"><i class="{{ $icon }}"></i></span>
												<p>{{ __($label) }}</p>
											</a>
										</div>
									@endforeach
								</div>
							</div>
						</li>
					@endif

					@if ($hasShop && $navUser && ($navUser->is_super_admin || $navUser->can('pos.access')))
						<li class="nav-item pos-nav">
							<a href="{{ route('pos.index') }}" class="btn btn-dark btn-md d-inline-flex align-items-center">
								<i class="ti ti-device-laptop me-1"></i>{{ __('app.menu.pos') }}
							</a>
						</li>
					@endif

					<!-- Language -->
					<li class="nav-item dropdown has-arrow flag-nav nav-item-box">
						<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="javascript:void(0);" role="button" title="{{ __('app.nav.language') }}">
							<i class="ti ti-language"></i>
						</a>
						<div class="dropdown-menu dropdown-menu-right">
							@foreach (['en' => 'English', 'sw' => 'Kiswahili'] as $code => $name)
								<form method="POST" action="{{ route('locale.switch', $code) }}">
									@csrf
									<button type="submit" class="dropdown-item w-100 text-start @if (app()->getLocale() === $code) active @endif">{{ $name }}</button>
								</form>
							@endforeach
						</div>
					</li>
					<!-- /Language -->

					<li class="nav-item nav-item-box">
						<a href="javascript:void(0);" id="btnFullscreen">
							<i class="ti ti-maximize"></i>
						</a>
					</li>

					<!-- Notifications: real alerts only (low stock, subscription) -->
					@if ($hasShop)
						@php
							$alerts = $navAlerts ?? ['low' => collect(), 'low_count' => 0];
							$navSubscription = \App\Support\Subscription::current();
							$subscriptionNotice = $navSubscription->state() !== \App\Support\Subscription::ACTIVE || $navSubscription->shouldWarn();
							$alertCount = $alerts['low_count'] + ($subscriptionNotice ? 1 : 0);
						@endphp
						<li class="nav-item dropdown nav-item-box">
							<a href="javascript:void(0);" class="dropdown-toggle nav-link" data-bs-toggle="dropdown">
								<i class="ti ti-bell"></i>
								@if ($alertCount > 0)<span class="badge rounded-pill">{{ $alertCount }}</span>@endif
							</a>
							<div class="dropdown-menu notifications">
								<div class="topnav-dropdown-header">
									<h5 class="notification-title">{{ __('app.nav.notifications') }}</h5>
								</div>
								<div class="noti-content">
									<ul class="notification-list">
										@if ($subscriptionNotice)
											<li class="notification-message">
												<div class="media d-flex p-2">
													<span class="avatar flex-shrink-0 bg-warning-transparent text-warning"><i class="ti ti-calendar-due fs-18"></i></span>
													<div class="flex-grow-1 ms-2">
														<p class="noti-details mb-0">
															@if ($navSubscription->state() === \App\Support\Subscription::READONLY){{ __('subscription.readonly_banner') }}
															@elseif ($navSubscription->state() === \App\Support\Subscription::GRACE){{ __('subscription.grace_banner', ['date' => $navSubscription->graceEndsAt()?->format('Y-m-d')]) }}
															@else{{ __('subscription.renew_banner', ['date' => $navSubscription->deadline()?->format('Y-m-d'), 'days' => $navSubscription->daysLeft()]) }}@endif
														</p>
													</div>
												</div>
											</li>
										@endif
										@foreach ($alerts['low'] as $product)
											<li class="notification-message">
												<a href="{{ route('stock.index') }}">
													<div class="media d-flex">
														<span class="avatar flex-shrink-0 bg-orange-transparent text-orange"><i class="ti ti-box fs-18"></i></span>
														<div class="flex-grow-1">
															<p class="noti-details mb-0"><span class="noti-title">{{ $product->name }}</span> {{ __('app.nav.low_stock', ['qty' => rtrim(rtrim((string) ($product->getAttribute('on_hand') ?? 0), '0'), '.') ?: '0']) }}</p>
														</div>
													</div>
												</a>
											</li>
										@endforeach
										@if ($alertCount === 0)
											<li class="p-3 text-center text-muted">{{ __('app.nav.no_notifications') }}</li>
										@endif
									</ul>
								</div>
								@if ($alerts['low_count'] > 0 && $navUser && ($navUser->is_super_admin || $navUser->can('inventory.view')))
									<div class="topnav-dropdown-footer d-flex align-items-center gap-3">
										<a href="{{ route('stock.index') }}" class="btn btn-primary btn-md w-100">{{ __('dashboard.view_stock') }}</a>
									</div>
								@endif
							</div>
						</li>
					@endif
					<!-- /Notifications -->

					@can('manage-settings')
						<li class="nav-item nav-item-box">
							<a href="{{ route('settings.index') }}"><i class="ti ti-settings"></i></a>
						</li>
					@endcan
					<li class="nav-item dropdown has-arrow main-drop profile-nav">
						<a href="javascript:void(0);" class="nav-link userset" data-bs-toggle="dropdown">
							<span class="user-info p-0">
								<span class="user-letter">
									<img src="{{ asset('assets/img/profiles/avator1.jpg') }}" alt="Img" class="img-fluid">
								</span>
							</span>
						</a>
						<div class="dropdown-menu menu-drop-user">
							<div class="profileset d-flex align-items-center">
								<span class="user-img me-2">
									<img src="{{ asset('assets/img/profiles/avator1.jpg') }}" alt="Img">
								</span>
								<div>
									<h6 class="fw-medium">{{ auth()->user()?->name }}</h6>
									<p>{{ auth()->user()?->displayRole() }}</p>
								</div>
							</div>
							<a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="ti ti-user-circle me-2"></i>{{ __('app.nav.my_profile') }}</a>
							@can('reports.view')
								@if (\App\Support\Plans::current()->allows('reports'))
								<a class="dropdown-item" href="{{ route('reports.index') }}"><i class="ti ti-file-text me-2"></i>{{ __('app.nav.reports') }}</a>
								@endif
							@endcan
							@can('manage-settings')
								<a class="dropdown-item" href="{{ route('settings.index') }}"><i class="ti ti-settings-2 me-2"></i>{{ __('app.nav.settings') }}</a>
							@endcan
							<hr class="my-2">
							<form method="POST" action="{{ route('logout') }}">
								@csrf
								<button type="submit" class="dropdown-item logout w-100 text-start"><i class="ti ti-logout me-2"></i>{{ __('app.nav.logout') }}</button>
							</form>
						</div>
					</li>
				</ul>
				<!-- /Header Menu -->

				<!-- Mobile Menu -->
				<div class="dropdown mobile-user-menu">
					<a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
						aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
					<div class="dropdown-menu dropdown-menu-right">
						<a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('app.nav.my_profile') }}</a>
						@can('manage-settings')
							<a class="dropdown-item" href="{{ route('settings.index') }}">{{ __('app.nav.settings') }}</a>
						@endcan
						<form method="POST" action="{{ route('logout') }}">
							@csrf
							<button type="submit" class="dropdown-item w-100 text-start">{{ __('app.nav.logout') }}</button>
						</form>
					</div>
				</div>
				<!-- /Mobile Menu -->
			</div>
		</div>
		<!-- /Header -->