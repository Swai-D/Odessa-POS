@extends('layouts.pos')

@section('title', __('app.menu.pos'))

@section('content')
<div class="main-wrapper pos-five">

		<!-- Header -->
		<div class="header pos-header">
		
			<!-- Logo -->
			 <div class="header-left active">
				<a href="{{ route('dashboard') }}" class="logo logo-normal">
					<img src="{{ asset('assets/img/logo.svg') }}"  alt="Img">
				</a>
				<a href="{{ route('dashboard') }}" class="logo logo-white">
					<img src="{{ asset('assets/img/logo-white.svg') }}"  alt="Img">
				</a>
				<a href="{{ route('dashboard') }}" class="logo-small">
					<img src="{{ asset('assets/img/logo-small.png') }}"  alt="Img">
				</a>
				<a href="{{ route('dashboard') }}" class="logo-small-white">
					<img src="{{ asset('assets/img/logo-small-white.png') }}"  alt="Img">
				</a>
			</div>
			<!-- /Logo -->
			
			<a id="mobile_btn" class="mobile_btn d-none" href="javascript:void(0);">
				<span class="bar-icon">
					<span></span>
					<span></span>
					<span></span>
				</span>
			</a>
			
			<!-- Header Menu -->
			<ul class="nav user-menu">

				<!-- Search -->
				<li class="nav-item time-nav">
					<span class="bg-teal text-white d-inline-flex align-items-center"><img src="{{ asset('assets/img/icons/clock-icon.svg') }}" alt="img" class="me-2"><span id="pos-clock">{{ now()->format('H:i:s') }}</span></span>
				</li>
				<!-- /Search -->
				
				<li class="nav-item pos-nav">
					<a href="{{ route('dashboard') }}" class="btn btn-purple btn-md d-inline-flex align-items-center">
						<i class="ti ti-world me-1"></i>{{ __('app.menu.dashboard') }}
					</a>
				</li>


				
				<li class="nav-item nav-item-box">
					<a href="javascript:void(0);" id="btnFullscreen" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Maximize" >
						<i class="ti ti-maximize"></i>
					</a>
				</li>
				@can('manage-settings')
					<li class="nav-item nav-item-box" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ __('app.nav.settings') }}">
						<a href="{{ route('settings.index') }}"><i class="ti ti-settings"></i></a>
					</li>
				@endcan
				<li class="nav-item dropdown has-arrow main-drop profile-nav">
					<a href="javascript:void(0);" class="nav-link userset" data-bs-toggle="dropdown">
						<span class="user-info p-0">
							<span class="user-letter">
								<img src="{{ asset('user.png') }}" alt="User profile" class="img-fluid">
							</span>
						</span>
					</a>
					<div class="dropdown-menu menu-drop-user">
						<div class="profilename">
							<div class="profileset">
								<span class="user-img"><img src="{{ asset('user.png') }}" alt="User profile">
									<span class="status online"></span></span>
								<div class="profilesets">
									<h6>{{ auth()->user()?->name }}</h6>
									<h5>{{ auth()->user()?->displayRole() }}</h5>
								</div>
							</div>
							<hr class="m-0">
							<a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="me-2" data-feather="user"></i>{{ __('app.nav.my_profile') }}</a>
							@can('manage-settings')
								<a class="dropdown-item" href="{{ route('settings.index') }}"><i class="me-2" data-feather="settings"></i>{{ __('app.nav.settings') }}</a>
							@endcan
							<hr class="m-0">
							<form method="POST" action="{{ route('logout') }}">
								@csrf
								<button type="submit" class="dropdown-item logout w-100 text-start"><img src="{{ asset('assets/img/icons/log-out.svg') }}" class="me-2" alt="img">{{ __('app.nav.logout') }}</button>
							</form>
						</div>
					</div>
				</li>
			</ul>
			<!-- /Header Menu -->
			
			<!-- Mobile Menu -->
			<div class="dropdown mobile-user-menu">
				<a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
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
		<!-- Header -->


		
		<div class="page-wrapper pos-pg-wrapper ms-0">
			<div class="content pos-design p-0">

				<div class="row pos-wrapper">

					<!-- Products -->
					<div class="col-md-12 col-lg-7 col-xl-8 d-flex">
						<div class="pos-categories tabs_wrapper p-0 flex-fill">
							<div class="content-wrap">
								<div class="tab-wrap">
									<div class="swiper pos-category5">
										<ul class="tabs swiper-wrapper">
											<li id="cat-all" class="active swiper-slide" data-pos-category="">
												<a href="javascript:void(0);">
													<span class="d-inline-flex align-items-center justify-content-center fw-bold bg-light text-gray-9 rounded-circle" style="width:56px;height:56px;font-size:18px;">{{ strtoupper(mb_substr(__('pos.screen.all'), 0, 2)) }}</span>
												</a>
												<h6><a href="javascript:void(0);">{{ __('pos.screen.all') }}</a></h6>
											</li>
											@foreach ($categories as $category)
											<li id="cat-{{ $category->id }}" class="swiper-slide" data-pos-category="{{ $category->id }}">
												<a href="javascript:void(0);">
													<span class="d-inline-flex align-items-center justify-content-center fw-bold bg-light text-gray-9 rounded-circle" style="width:56px;height:56px;font-size:18px;">{{ strtoupper(mb_substr($category->name, 0, 2)) }}</span>
												</a>
												<h6><a href="javascript:void(0);">{{ $category->name }}</a></h6>
											</li>
											@endforeach
										</ul>
									</div>
								</div>
								<div class="tab-content-wrap">
									<div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
										<div class="mb-3">
											<h5 class="mb-1">{{ __('pos.screen.welcome', ['name' => auth()->user()?->name]) }}</h5>
											<p>{{ now()->translatedFormat('F j, Y') }}</p>
										</div>
										<div class="d-flex align-items-center flex-wrap mb-2">
											@if ($warehouses->count() > 1)
											<select class="form-select me-3 mb-2" id="pos-warehouse" style="width:auto;" aria-label="{{ __('pos.screen.warehouse') }}">
												@foreach ($warehouses as $warehouse)
													<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
												@endforeach
											</select>
											@endif
											<div class="input-icon-start search-pos position-relative mb-2 me-3">
												<span class="input-icon-addon">
													<i class="ti ti-search"></i>
												</span>
												<input type="text" class="form-control" id="pos-search" autocomplete="off" placeholder="{{ __('pos.screen.search') }}">
											</div>
										</div>
									</div>
									<div class="pos-products">
										<div class="tabs_container">
											<div class="tab_content active" data-tab="all">
												<div class="row g-3" id="pos-grid"></div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- /Products -->

					<!-- Order Details -->
					<div class="col-md-12 col-lg-5 col-xl-4 ps-0 theiaStickySidebar d-lg-flex">
						<aside class="product-order-list bg-secondary-transparent flex-fill">
							<div class="card">
								<div class="card-body">
									<div class="order-head d-flex align-items-center justify-content-between w-100">
										<div>
											<h3>{{ __('pos.screen.order_list') }}</h3>
										</div>
										<div class="d-flex align-items-center gap-2">
											<a class="link-danger fs-16" href="javascript:void(0);" data-pos-action="clear"><i class="ti ti-trash-x-filled"></i></a>
										</div>
									</div>
									<div id="pos-alert" style="display:none;"></div>
									<div class="customer-info block-section">
										<h5 class="mb-2">{{ __('pos.screen.customer_info') }}</h5>
										<div class="d-flex align-items-center gap-2">
											<div class="flex-grow-1 position-relative">
												<input type="search" class="form-control mb-1" id="pos-customer-search" placeholder="{{ __('pos.screen.search_customer') }}" autocomplete="off">
												<div class="dropdown-menu w-100" id="pos-customer-results" role="listbox" style="max-height:200px;overflow-y:auto;"></div>
												<select class="form-select" id="pos-customer">
													<option value="">{{ __('pos.screen.walk_in') }}</option>
													@foreach ($customers as $customer)
														<option value="{{ $customer->id }}">{{ $customer->name }}@if ($customer->phone) ({{ $customer->phone }})@endif</option>
													@endforeach
												</select>
											</div>
											@if ($canCreateCustomer)
											<a href="javascript:void(0);" class="btn btn-teal btn-icon fs-20" data-bs-toggle="modal" data-bs-target="#pos-customer-modal" title="{{ __('pos.screen.add_customer') }}"><i class="ti ti-user-plus"></i></a>
											@endif
											<a href="javascript:void(0);" class="btn btn-info btn-icon fs-20" data-pos-action="scan"><i class="ti ti-scan"></i></a>
										</div>
									</div>
									<div class="product-added block-section">
										<div class="head-text d-flex align-items-center justify-content-between mb-3">
											<div class="d-flex align-items-center">
												<h5 class="me-2">{{ __('pos.screen.order_details') }}</h5>
												<div class="badge bg-light text-gray-9 fs-12 fw-semibold py-2 border rounded">{{ __('pos.screen.items') }} : <span class="text-teal" id="pos-items-count">0</span></div>
											</div>
											<a href="javascript:void(0);" class="d-flex align-items-center bg-danger text-white clear-icon fs-10 fw-medium" data-pos-action="clear">{{ __('pos.screen.clear_all') }}</a>
										</div>
										<div class="product-wrap">
											<div class="empty-cart" id="pos-empty">
												<div class="fs-24 mb-1">
													<i class="ti ti-shopping-cart"></i>
												</div>
												<p class="fw-bold">{{ __('pos.screen.no_products') }}</p>
											</div>
											<div class="product-list border-0 p-0" id="pos-list" style="display:none;">
												<div class="table-responsive">
													<table class="table table-borderless">
														<thead>
															<tr>
																<th class="fw-bold bg-light">{{ __('pos.screen.item') }}</th>
																<th class="fw-bold bg-light">{{ __('pos.screen.qty') }}</th>
																<th class="fw-bold bg-light text-end">{{ __('pos.screen.cost') }}</th>
															</tr>
														</thead>
														<tbody id="pos-cart-body"></tbody>
													</table>
												</div>
											</div>
										</div>
										<div class="discount-item d-flex align-items-center justify-content-between bg-purple-transparent mt-3 flex-wrap gap-2" id="pos-discount-chip" style="display:none;">
											<div class="d-flex align-items-center">
												<span class="bg-purple discount-icon br-5 flex-shrink-0 me-2">
													<img src="{{ asset('assets/img/icons/discount-icon.svg') }}" alt="img">
												</span>
												<div>
													<h6 class="fs-14 fw-bold text-purple mb-0" id="pos-discount-label"></h6>
												</div>
											</div>
											<a href="javascript:void(0);" class="close-icon" data-pos-action="remove-discount"><i class="ti ti-trash"></i></a>
										</div>
									</div>
									<div class="order-total bg-total bg-white p-0">
										<h5 class="mb-3">{{ __('pos.screen.payment_summary') }}</h5>
										<table class="table table-responsive table-borderless">
											<tr>
												<td>{{ __('pos.screen.tax') }}</td>
												<td class="text-gray-9 text-end" id="pos-tax">0.00</td>
											</tr>
											<tr>
												<td><span class="text-danger">{{ __('pos.screen.discount') }}</span><a href="javascript:void(0);" class="ms-3 link-default" data-bs-toggle="modal" data-bs-target="#pos-discount"><i class="ti ti-edit"></i></a></td>
												<td class="text-danger text-end" id="pos-discount-total">0.00</td>
											</tr>
											<tr>
												<td>{{ __('pos.screen.subtotal') }}</td>
												<td class="text-gray-9 text-end" id="pos-subtotal">0.00</td>
											</tr>
											<tr>
												<td class="fw-bold border-top border-dashed">{{ __('pos.screen.total') }}</td>
												<td class="text-gray-9 fw-bold text-end border-top border-dashed" id="pos-total">0.00</td>
											</tr>
										</table>
									</div>
								</div>
							</div>
							<div class="card payment-method">
								<div class="card-body">
									<h5 class="mb-3">{{ __('pos.screen.select_payment') }}</h5>
									<div class="row align-items-center methods g-2">
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="cash">
												<img src="{{ asset('assets/img/icons/cash-icon.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.methods.cash') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="card">
												<img src="{{ asset('assets/img/icons/card.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.methods.card') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="mobile_money">
												<img src="{{ asset('assets/img/icons/scan-icon.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.methods.mobile_money') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="bank_transfer">
												<img src="{{ asset('assets/img/icons/deposit.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.methods.bank_transfer') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="cheque">
												<img src="{{ asset('assets/img/icons/cheque.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.methods.cheque') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="credit">
												<img src="{{ asset('assets/img/icons/paylater.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.screen.pay_later') }}</p>
											</a>
										</div>
										<div class="col-sm-6 col-md-4 d-flex">
											<a href="javascript:void(0);" class="payment-item d-flex align-items-center justify-content-center p-2 flex-fill" data-pos-pay="split">
												<img src="{{ asset('assets/img/icons/split-bill.svg') }}" class="me-2" alt="img">
												<p class="fs-14 fw-medium">{{ __('pos.screen.split') }}</p>
											</a>
										</div>
									</div>
								</div>
							</div>
							<div class="btn-row d-flex align-items-center justify-content-between gap-3">
								<a href="javascript:void(0);" class="btn btn-white d-flex align-items-center justify-content-center flex-fill m-0" data-pos-action="hold"><i class="ti ti-player-pause me-2"></i>{{ __('pos.screen.hold') }}</a>
								<a href="javascript:void(0);" class="btn btn-secondary d-flex align-items-center justify-content-center flex-fill m-0" data-pos-action="pay"><i class="ti ti-cash-banknote me-2"></i>{{ __('pos.screen.pay') }}</a>
							</div>
						</aside>
					</div>
					<!-- /Order Details -->

				</div>

				<div class="pos-footer bg-white p-3 border-top">
					<div class="d-flex align-items-center justify-content-center flex-wrap gap-2">
						<a href="javascript:void(0);" class="btn btn-orange d-inline-flex align-items-center justify-content-center" data-pos-action="hold"><i class="ti ti-player-pause me-2"></i>{{ __('pos.screen.hold') }}</a>
						<a href="javascript:void(0);" class="btn btn-info d-inline-flex align-items-center justify-content-center" data-pos-action="clear"><i class="ti ti-trash me-2"></i>{{ __('pos.screen.void') }}</a>
						<a href="javascript:void(0);" class="btn btn-cyan d-flex align-items-center justify-content-center" data-pos-action="pay"><i class="ti ti-cash-banknote me-2"></i>{{ __('pos.screen.pay') }}</a>
						<a href="javascript:void(0);" class="btn btn-secondary d-inline-flex align-items-center justify-content-center" data-pos-action="held"><i class="ti ti-shopping-cart me-2"></i>{{ __('pos.screen.held_orders') }}</a>
						<a href="{{ route('sales.index') }}" class="btn btn-danger d-inline-flex align-items-center justify-content-center"><i class="ti ti-refresh-dot me-2"></i>{{ __('pos.screen.transactions') }}</a>
					</div>
				</div>
			</div>
		</div>

	</div>
	<!-- /Main Wrapper -->

	<!-- Odessa POS dialogs (built from the template's modal styles) -->
	<div class="modal fade modal-default pos-modal" id="pos-pay" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<div class="d-flex align-items-center justify-content-between w-100">
						<h5>{{ __('pos.screen.select_payment') }}</h5>
						<h4 class="text-primary mb-0" id="pos-pay-total">0.00</h4>
					</div>
					<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
				</div>
				<div class="modal-body">
					<div id="pos-pay-rows"></div>
					<a href="javascript:void(0);" class="btn btn-sm btn-white mb-3" id="pos-pay-add"><i class="ti ti-plus me-1"></i>{{ __('pos.screen.add_payment') }}</a>
					<div class="border-top pt-3">
						<div class="d-flex justify-content-between"><span>{{ __('pos.sales.paid') }}</span><span id="pos-pay-paid">0.00</span></div>
						<div class="d-flex justify-content-between"><span>{{ __('pos.screen.change') }}</span><span id="pos-pay-change">0.00</span></div>
						<div id="pos-pay-balance-row" class="d-flex justify-content-between fw-bold"><span>{{ __('pos.screen.balance_due') }}</span><span id="pos-pay-balance">0.00</span></div>
					</div>
					<div class="alert alert-danger py-2 px-3 fs-13 mt-3 mb-0" id="pos-pay-error" style="display:none;"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-white" data-bs-dismiss="modal">{{ __('pos.screen.cancel') }}</button>
					<button type="button" class="btn btn-primary" id="pos-pay-confirm">{{ __('pos.screen.complete_sale') }}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade modal-default pos-modal" id="pos-done" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-body text-center p-4">
					<div class="fs-36 text-success mb-2"><i class="ti ti-circle-check-filled"></i></div>
					<h4>{{ __('pos.screen.sale_done') }}</h4>
					<p class="mb-1"><span id="pos-done-number"></span></p>
					<p class="mb-1 fw-bold fs-18" id="pos-done-total"></p>
					<p class="mb-1" id="pos-done-change-row">{{ __('pos.screen.change') }}: <span id="pos-done-change"></span></p>
					<p class="mb-3 text-danger" id="pos-done-balance-row">{{ __('pos.screen.balance_due') }}: <span id="pos-done-balance"></span></p>
					<div class="d-flex justify-content-center flex-wrap gap-2">
						<a href="#" target="_blank" rel="noopener" class="btn btn-white" id="pos-done-receipt"><i class="ti ti-printer me-1"></i>{{ __('pos.sales.thermal') }}</a>
						<a href="#" target="_blank" rel="noopener" class="btn btn-white" id="pos-done-invoice"><i class="ti ti-file-invoice me-1"></i>{{ __('pos.screen.receipt') }}</a>
						<a href="javascript:void(0);" class="btn btn-primary" id="pos-done-new">{{ __('pos.screen.new_sale') }}</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade modal-default pos-modal" id="pos-discount" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5>{{ __('pos.screen.discount_title') }}</h5>
					<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<select class="form-select" id="pos-discount-type">
							<option value="percent">{{ __('pos.screen.percent') }}</option>
							<option value="fixed">{{ __('pos.screen.fixed') }}</option>
						</select>
					</div>
					<input type="text" inputmode="decimal" class="form-control" id="pos-discount-value" placeholder="0" aria-describedby="pos-discount-error">
					<p class="text-danger small mt-2 mb-0" id="pos-discount-error" role="alert" style="display:none;"></p>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-white" data-bs-dismiss="modal">{{ __('pos.screen.cancel') }}</button>
					<button type="button" class="btn btn-primary" id="pos-discount-apply">{{ __('pos.screen.save') }}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade modal-default pos-modal" id="pos-hold" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5>{{ __('pos.screen.hold') }}</h5>
					<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
				</div>
				<div class="modal-body">
					<input type="text" class="form-control" id="pos-hold-reference" maxlength="100" placeholder="{{ __('pos.screen.hold_reference') }}">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-white" data-bs-dismiss="modal">{{ __('pos.screen.cancel') }}</button>
					<button type="button" class="btn btn-primary" id="pos-hold-save">{{ __('pos.screen.hold') }}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade modal-default pos-modal" id="pos-held" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5>{{ __('pos.screen.held_orders') }}</h5>
					<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
				</div>
				<div class="modal-body">
					<div class="table-responsive">
						<table class="table">
							<thead><tr><th>{{ __('pos.screen.reference') }}</th><th>{{ __('pos.sales.customer') }}</th><th>{{ __('pos.screen.items') }}</th><th>{{ __('pos.sales.date') }}</th><th></th></tr></thead>
							<tbody id="pos-held-body"></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	@if ($canCreateCustomer)
	<div class="modal fade modal-default pos-modal" id="pos-customer-modal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5>{{ __('pos.screen.add_customer') }}</h5>
					<button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="ti ti-x"></i></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">{{ __('pos.screen.customer_name') }}<span class="text-danger ms-1">*</span></label>
						<input type="text" class="form-control" id="pos-customer-name" maxlength="255">
					</div>
					<div class="mb-3">
						<label class="form-label">{{ __('pos.screen.customer_phone') }}</label>
						<input type="text" class="form-control" id="pos-customer-phone" maxlength="40">
					</div>
					<div class="alert alert-danger py-2 px-3 fs-13 mb-0" id="pos-customer-error" style="display:none;"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-white" data-bs-dismiss="modal">{{ __('pos.screen.cancel') }}</button>
					<button type="button" class="btn btn-primary" id="pos-customer-save">{{ __('pos.screen.save') }}</button>
				</div>
			</div>
		</div>
	</div>
	@endif

@endsection

@push('extra-js')
<script>
	(function () {
		var clock = document.getElementById('pos-clock');
		if (clock) { setInterval(function () { clock.textContent = new Date().toTimeString().slice(0, 8); }, 1000); }
	})();
</script>
<script>
	window.POS = {
		csrf: @json(csrf_token()),
		currency: @json($currency),
		warehouseId: @json((string) ($warehouses->first()?->id ?? '')),
		methods: @json($paymentMethods),
		methodLabels: @json(__('pos.methods')),
		walkIn: @json(__('pos.screen.walk_in')),
		sessionExpired: @json(__('pos.screen.session_expired')),
		errors: @json(__('pos.errors')),
		i18n: @json(__('pos.screen')),
		routes: {
			products: @json(route('pos.products')),
			checkout: @json(route('pos.checkout')),
			customers: @json(route('pos.customers.store')),
			customerSearch: @json(route('pos.customers.index')),
			heldIndex: @json(route('pos.held.index')),
			heldStore: @json(route('pos.held.store')),
			heldBase: @json(url('/pos/held'))
		}
	};
</script>
<script src="{{ asset('js/pos-calc.js') }}"></script>
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
@endpush
