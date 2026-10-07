<!-- Sidebar -->
		<div class="sidebar" id="sidebar">
			<!-- Logo -->
			<div class="sidebar-logo active">
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
				<a id="toggle_btn" href="javascript:void(0);">
					<i data-feather="chevrons-left" class="feather-16"></i>
				</a>
			</div>
			<!-- /Logo -->
			<div class="modern-profile p-3 pb-0">
				<div class="text-center rounded bg-light p-3 mb-4 user-profile">
					<div class="avatar avatar-lg online mb-3">
						<img src="{{ asset('assets/img/customer/customer15.jpg') }}" alt="Img" class="img-fluid rounded-circle">
					</div>
					<h6 class="fs-14 fw-bold mb-1">{{ auth()->user()?->name }}</h6>
					<p class="fs-12 mb-0">{{ auth()->user()?->displayRole() }}</p>
				</div>
				<div class="sidebar-nav mb-3">
					<ul class="nav nav-tabs nav-tabs-solid nav-tabs-rounded nav-justified bg-transparent" role="tablist">
						<li class="nav-item"><a class="nav-link active border-0" href="{{ route('dashboard') }}#">Menu</a></li>
						<li class="nav-item"><a class="nav-link border-0" href="chat.html">Chats</a></li>
						<li class="nav-item"><a class="nav-link border-0" href="email.html">Inbox</a></li>
					</ul>
				</div>
			</div>
			<div class="sidebar-header p-3 pb-0 pt-2">
				<div class="text-center rounded bg-light p-2 mb-4 sidebar-profile d-flex align-items-center">
					<div class="avatar avatar-md online">
						<img src="{{ asset('assets/img/customer/customer15.jpg') }}" alt="Img" class="img-fluid rounded-circle">
					</div>
					<div class="text-start sidebar-profile-info ms-2">
						<h6 class="fs-14 fw-bold mb-1">{{ auth()->user()?->name }}</h6>
						<p class="fs-12">{{ auth()->user()?->displayRole() }}</p>
					</div>
				</div>
				<div class="d-flex align-items-center justify-content-between menu-item mb-3">
					<div>
						<a href="{{ route('dashboard') }}" class="btn btn-sm btn-icon bg-light">
							<i class="ti ti-layout-grid-remove"></i>
						</a>
					</div>
					<div>
						<a href="chat.html" class="btn btn-sm btn-icon bg-light">
							<i class="ti ti-brand-hipchat"></i>
						</a>
					</div>
					<div>
						<a href="email.html" class="btn btn-sm btn-icon bg-light position-relative">
							<i class="ti ti-message"></i>
						</a>
					</div>
					<div class="notification-item">
						<a href="activities.html" class="btn btn-sm btn-icon bg-light position-relative">
							<i class="ti ti-bell"></i>
							<span class="notification-status-dot"></span>
						</a>
					</div>
					<div class="me-0">
						<a href="general-settings.html" class="btn btn-sm btn-icon bg-light">
							<i class="ti ti-settings"></i>
						</a>
					</div>
				</div>
			</div>
			<div class="sidebar-inner slimscroll">
				<div id="sidebar-menu" class="sidebar-menu">
    @php
        $menuUser = auth()->user();
        $plan = \App\Support\Plans::current();
        $hasShop = app(\App\Support\Tenancy\TenantContext::class)->get() !== null;
        $canSee = function (array $item) use ($menuUser, $plan, $hasShop): bool {
            if ($menuUser === null) {
                return false;
            }
            // Platform items are for super admins; shop items need a shop.
            if ($item['platform'] ?? false) {
                return (bool) $menuUser->is_super_admin;
            }

            return $hasShop
                && ($menuUser->is_super_admin || $menuUser->can($item['permission']))
                && (! isset($item['feature']) || $plan->allows($item['feature']));
        };
    @endphp
    <ul>
        @foreach (config('menu') as $section)
            @php($items = collect($section['items'])->filter($canSee))
            @if ($items->isNotEmpty())
                <li class="submenu-open">
                    <h6 class="submenu-hdr">{{ __($section['label']) }}</h6>
                    <ul>
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs(...$item['match'])])>
                                    <i class="{{ $item['icon'] }} fs-16 me-2"></i>
                                    <span>{{ __($item['label']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif
        @endforeach
    </ul>
</div>
</div>
</div>
<!-- /Sidebar -->