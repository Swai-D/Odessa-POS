@extends('layouts.pos')

@section('title', __('app.menu.pos'))

@section('content')
<div class="main-wrapper pos-five">

		<!-- Header -->
		<div class="header pos-header">
		
			<!-- Logo -->
			 <div class="header-left active">
				<a href="index.html" class="logo logo-normal">
					<img src="{{ asset('assets/img/logo.svg') }}"  alt="Img">
				</a>
				<a href="index.html" class="logo logo-white">
					<img src="{{ asset('assets/img/logo-white.svg') }}"  alt="Img">
				</a>
				<a href="index.html" class="logo-small">
					<img src="{{ asset('assets/img/logo-small.png') }}"  alt="Img">
				</a>
				<a href="index.html" class="logo-small-white">
					<img src="{{ asset('assets/img/logo-small-white.png') }}"  alt="Img">
				</a>
			</div>
			<!-- /Logo -->
			
			<a id="mobile_btn" class="mobile_btn d-none" href="pos.html#sidebar">
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
					<span class="bg-teal text-white d-inline-flex align-items-center"><img src="{{ asset('assets/img/icons/clock-icon.svg') }}" alt="img" class="me-2">09:25:32</span>
				</li>
				<!-- /Search -->
				
				<li class="nav-item pos-nav">
					<a href="index.html" class="btn btn-purple btn-md d-inline-flex align-items-center">
						<i class="ti ti-world me-1"></i>Dashboard
					</a>
				</li>

				<!-- Select Store -->
				<li class="nav-item dropdown has-arrow main-drop select-store-dropdown">
					<a href="javascript:void(0);" class="dropdown-toggle nav-link select-store"
						data-bs-toggle="dropdown">
						<span class="user-info">
							<span class="user-letter">
								<img src="{{ asset('assets/img/store/store-01.png') }}" alt="Store Logo" class="img-fluid">
							</span>
							<span class="user-detail">
								<span class="user-name">Freshmart</span>
							</span>
						</span>
					</a>
					<div class="dropdown-menu dropdown-menu-right">
						<a href="javascript:void(0);" class="dropdown-item">
							<img src="{{ asset('assets/img/store/store-01.png') }}" alt="Store Logo" class="img-fluid">Freshmart
						</a>
						<a href="javascript:void(0);" class="dropdown-item">
							<img src="{{ asset('assets/img/store/store-02.png') }}" alt="Store Logo" class="img-fluid">Grocery Apex
						</a>
						<a href="javascript:void(0);" class="dropdown-item">
							<img src="{{ asset('assets/img/store/store-03.png') }}" alt="Store Logo" class="img-fluid">Grocery Bevy
						</a>
						<a href="javascript:void(0);" class="dropdown-item">
							<img src="{{ asset('assets/img/store/store-04.png') }}" alt="Store Logo" class="img-fluid">Grocery Eden
						</a>
					</div>
				</li>
				<!-- /Select Store -->
				
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
								<img src="{{ asset('assets/img/profiles/avator1.jpg') }}" alt="Img" class="img-fluid">
							</span>
						</span>
					</a>
					<div class="dropdown-menu menu-drop-user">
						<div class="profilename">
							<div class="profileset">
								<span class="user-img"><img src="{{ asset('assets/img/profiles/avator1.jpg') }}" alt="Img">
									<span class="status online"></span></span>
								<div class="profilesets">
									<h6>{{ auth()->user()->name }}</h6>
									<h5>{{ auth()->user()->displayRole() }}</h5>
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

		<!-- Sidebar -->
		<div class="sidebar d-none" id="sidebar">
			<!-- Logo -->
			<div class="sidebar-logo">
				<a href="index.html" class="logo logo-normal">
					<img src="{{ asset('assets/img/logo.svg') }}" alt="Img">
				</a>
				<a href="index.html" class="logo logo-white">
					<img src="{{ asset('assets/img/logo-white.svg') }}" alt="Img">
				</a>
				<a href="index.html" class="logo-small">
					<img src="{{ asset('assets/img/logo-small.png') }}" alt="Img">
				</a>
				<a href="index.html" class="logo-small-white">
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
					<h6 class="fs-14 fw-bold mb-1">Adrian Herman</h6>
					<p class="fs-12 mb-0">System Admin</p>
				</div>
				<div class="sidebar-nav mb-3">
					<ul class="nav nav-tabs nav-tabs-solid nav-tabs-rounded nav-justified bg-transparent" role="tablist">
						<li class="nav-item"><a class="nav-link active border-0" href="pos.html#">Menu</a></li>
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
						<h6 class="fs-14 fw-bold mb-1">Adrian Herman</h6>
						<p class="fs-12">System Admin</p>
					</div>
				</div>
				<div class="d-flex align-items-center justify-content-between menu-item mb-3">
					<div>
						<a href="index.html" class="btn btn-sm btn-icon bg-light">
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
					<ul>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Main</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);" class="subdrop active"><i class="ti ti-layout-grid fs-16 me-2"></i><span>Dashboard</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="index.html" class="active">Admin Dashboard</a></li>
										<li><a href="admin-dashboard.html">Admin Dashboard 2</a></li>
										<li><a href="sales-dashboard.html">Sales Dashboard</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-user-edit fs-16 me-2"></i><span>Super Admin</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="dashboard.html">Dashboard</a></li>
										<li><a href="companies.html">Companies</a></li>
										<li><a href="subscription.html">Subscriptions</a></li>
										<li><a href="packages.html">Packages</a></li>
										<li><a href="domain.html">Domain</a></li>
										<li><a href="purchase-transaction.html">Purchase Transaction</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-brand-apple-arcade fs-16 me-2"></i><span>Application</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="chat.html">Chat</a></li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Call<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="video-call.html">Video Call</a></li>
												<li><a href="audio-call.html">Audio Call</a></li>
												<li><a href="call-history.html">Call History</a></li>
											</ul>
										</li>
										<li><a href="calendar.html">Calendar</a></li>
										<li><a href="contacts.html">Contacts</a></li>
										<li><a href="email.html">Email</a></li>
										<li><a href="todo.html">To Do</a></li>
										<li><a href="notes.html">Notes</a></li>
										<li><a href="file-manager.html">File Manager</a></li>
										<li><a href="projects.html">Projects</a></li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Ecommerce<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="products.html">Products</a></li>
												<li><a href="orders.html">Orders</a></li>
												<li><a href="customers.html">Customers</a></li>
												<li><a href="cart.html">Cart</a></li>
												<li><a href="checkout.html">Checkout</a></li>
												<li><a href="wishlist.html">Wishlist</a></li>
												<li><a href="reviews.html">Reviews</a></li>
											</ul>
										</li>
										<li><a href="social-feed.html">Social Feed</a></li>
										<li><a href="search-list.html">Search List</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-layout-sidebar-right-collapse fs-16 me-2"></i><span>Layouts</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="layout-horizontal.html">Horizontal</a></li>
										<li><a href="layout-detached.html">Detached</a></li>
										<li><a href="layout-two-column.html">Two Column</a></li>
										<li><a href="layout-hovered.html">Hovered</a></li>
										<li><a href="layout-boxed.html">Boxed</a></li>
										<li><a href="layout-rtl.html">RTL</a></li>
										<li><a href="layout-dark.html">Dark</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Inventory</h6>
							<ul>
								<li><a href="product-list.html"><i class="ti ti-box fs-16 me-2"></i><span>Products</span></a></li>
								<li><a href="add-product.html"><i class="ti ti-table-plus fs-16 me-2"></i><span>Create Product</span></a></li>
								<li><a href="expired-products.html"><i class="ti ti-progress-alert fs-16 me-2"></i><span>Expired Products</span></a></li>
								<li><a href="low-stocks.html"><i class="ti ti-trending-up-2 fs-16 me-2"></i><span>Low Stocks</span></a></li>
								<li><a href="category-list.html"><i class="ti ti-list-details fs-16 me-2"></i><span>Category</span></a></li>
								<li><a href="sub-categories.html"><i class="ti ti-carousel-vertical fs-16 me-2"></i><span>Sub Category</span></a></li>
								<li><a href="brand-list.html"><i class="ti ti-triangles fs-16 me-2"></i><span>Brands</span></a></li>
								<li><a href="units.html"><i class="ti ti-brand-unity fs-16 me-2"></i><span>Units</span></a></li>
								<li><a href="varriant-attributes.html"><i class="ti ti-checklist fs-16 me-2"></i><span>Variant Attributes</span></a></li>
								<li><a href="warranty.html"><i class="ti ti-certificate fs-16 me-2"></i><span>Warranties</span></a></li>
								<li><a href="barcode.html"><i class="ti ti-barcode fs-16 me-2"></i><span>Print Barcode</span></a></li>
								<li><a href="qrcode.html"><i class="ti ti-qrcode fs-16 me-2"></i><span>Print QR Code</span></a></li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Stock</h6>
							<ul>
								<li><a href="manage-stocks.html"><i class="ti ti-stack-3 fs-16 me-2"></i><span>Manage Stock</span></a></li>
								<li><a href="stock-adjustment.html"><i class="ti ti-stairs-up fs-16 me-2"></i><span>Stock Adjustment</span></a></li>
								<li><a href="stock-transfer.html"><i class="ti ti-stack-pop fs-16 me-2"></i><span>Stock Transfer</span></a></li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Sales</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-layout-grid fs-16 me-2"></i><span>Sales</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="online-orders.html">Online Orders</a></li>
										<li><a href="pos-orders.html">POS Orders</a></li>
									</ul>
								</li>
								<li><a href="invoice.html"><i class="ti ti-file-invoice fs-16 me-2"></i><span>Invoices</span></a></li>
								<li><a href="sales-returns.html"><i class="ti ti-receipt-refund fs-16 me-2"></i><span>Sales Return</span></a></li>
								<li><a href="quotation-list.html"><i class="ti ti-files fs-16 me-2"></i><span>Quotation</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-device-laptop fs-16 me-2"></i><span>POS</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="pos.html">POS 1</a></li>
										<li><a href="pos-2.html">POS 2</a></li>
										<li><a href="pos-3.html">POS 3</a></li>
										<li><a href="pos-4.html">POS 4</a></li>
										<li><a href="pos-5.html">POS 5</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/restaurant-pos/html/index.html" target="_blank">POS 6</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/laundry-pos/html/template/index.html" target="_blank">POS 7</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/pharmacy-pos/html/index.html" target="_blank">POS 8</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Promo</h6>
							<ul>
								<li><a href="coupons.html"><i class="ti ti-ticket fs-16 me-2"></i><span>Coupons</span></a></li>
								<li><a href="gift-cards.html"><i class="ti ti-cards fs-16 me-2"></i><span>Gift Cards</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-file-percent fs-16 me-2"></i><span>Discount</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="discount-plan.html">Discount Plan</a></li>
										<li><a href="discount.html">Discount</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Purchases</h6>
							<ul>
								<li><a href="purchase-list.html"><i class="ti ti-shopping-bag fs-16 me-2"></i><span>Purchases</span></a></li>
								<li><a href="purchase-order-report.html"><i class="ti ti-file-unknown fs-16 me-2"></i><span>Purchase Order</span></a></li>
								<li><a href="purchase-returns.html"><i class="ti ti-file-upload fs-16 me-2"></i><span>Purchase Return</span></a></li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Finance & Accounts</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-file-stack fs-16 me-2"></i><span>Expenses</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="expense-list.html">Expenses</a></li>
										<li><a href="expense-category.html">Expense Category</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-file-pencil fs-16 me-2"></i><span>Income</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="income.html">Income</a></li>
										<li><a href="income-category.html">Income Category</a></li>
									</ul>
								</li>
								<li><a href="account-list.html"><i class="ti ti-building-bank fs-16 me-2"></i><span>Bank Accounts</span></a></li>
								<li><a href="money-transfer.html"><i class="ti ti-moneybag fs-16 me-2"></i><span>Money Transfer</span></a></li>
								<li><a href="balance-sheet.html"><i class="ti ti-report-money fs-16 me-2"></i><span>Balance Sheet</span></a></li>
								<li><a href="trial-balance.html"><i class="ti ti-alert-circle fs-16 me-2"></i><span>Trial Balance</span></a></li>
								<li><a href="cash-flow.html"><i class="ti ti-zoom-money fs-16 me-2"></i><span>Cash Flow</span></a></li>
								<li><a href="account-statement.html"><i class="ti ti-file-infinity fs-16 me-2"></i><span>Account Statement</span></a></li>

							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Peoples</h6>
							<ul>
								<li><a href="customers.html"><i class="ti ti-users-group fs-16 me-2"></i><span>Customers</span></a></li>
								<li><a href="billers.html"><i class="ti ti-user-up fs-16 me-2"></i><span>Billers</span></a></li>
								<li><a href="suppliers.html"><i class="ti ti-user-dollar fs-16 me-2"></i><span>Suppliers</span></a></li>
								<li><a href="store-list.html"><i class="ti ti-home-bolt fs-16 me-2"></i><span>Stores</span></a></li>
								<li><a href="warehouse.html"><i class="ti ti-archive fs-16 me-2"></i><span>Warehouses</span></a>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">HRM</h6>
							<ul>
								<li><a href="employees-grid.html"><i class="ti ti-user fs-16 me-2"></i><span>Employees</span></a></li>
								<li><a href="department-grid.html"><i class="ti ti-compass fs-16 me-2"></i><span>Departments</span></a></li>
								<li><a href="designation.html"><i class="ti ti-git-merge fs-16 me-2"></i><span>Designation</span></a></li>
								<li><a href="shift.html"><i class="ti ti-arrows-shuffle fs-16 me-2"></i><span>Shifts</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-user-cog fs-16 me-2"></i><span>Attendence</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="attendance-employee.html">Employee</a></li>
										<li><a href="attendance-admin.html">Admin</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-calendar fs-16 me-2"></i><span>Leaves</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="leaves-admin.html">Admin Leaves</a></li>
										<li><a href="leaves-employee.html">Employee Leaves</a></li>
										<li><a href="leave-types.html">Leave Types</a></li>
									</ul>
								</li>
								<li><a href="holidays.html"><i class="ti ti-calendar-share fs-16 me-2"></i><span>Holidays</span></a>
								</li>
								<li class="submenu">
									<a href="employee-salary.html"><i class="ti ti-file-dollar fs-16 me-2"></i><span>Payroll</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="employee-salary.html">Employee Salary</a></li>
										<li><a href="payslip.html">Payslip</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Reports</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-chart-bar fs-16 me-2"></i><span>Sales Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="sales-report.html">Sales Report</a></li>
										<li><a href="best-seller.html">Best Seller</a></li>
									</ul>
								</li>
								<li><a href="purchase-report.html"><i class="ti ti-chart-pie-2 fs-16 me-2"></i><span>Purchase report</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-triangle-inverted fs-16 me-2"></i><span>Inventory Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="inventory-report.html">Inventory Report</a></li>
										<li><a href="stock-history.html">Stock History</a></li>
										<li><a href="sold-stock.html">Sold Stock</a></li>
									</ul>
								</li>
								<li><a href="invoice-report.html"><i class="ti ti-businessplan fs-16 me-2"></i><span>Invoice Report</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-user-star fs-16 me-2"></i><span>Supplier Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="supplier-report.html">Supplier Report</a></li>
										<li><a href="supplier-due-report.html">Supplier Due Report</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-report fs-16 me-2"></i><span>Customer Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="customer-report.html">Customer Report</a></li>
										<li><a href="customer-due-report.html">Customer Due Report</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-report-analytics fs-16 me-2"></i><span>Product Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="product-report.html">Product Report</a></li>
										<li><a href="product-expiry-report.html">Product Expiry Report</a></li>
										<li><a href="product-quantity-alert.html">Product Quantity Alert</a></li>
									</ul>
								</li>
								<li><a href="expense-report.html"><i class="ti ti-file-vector fs-16 me-2"></i><span>Expense Report</span></a></li>
								<li><a href="income-report.html"><i class="ti ti-chart-ppf fs-16 me-2"></i><span>Income Report</span></a></li>
								<li><a href="tax-reports.html"><i class="ti ti-chart-dots-2 fs-16 me-2"></i><span>Tax Report</span></a></li>
								<li><a href="profit-and-loss.html"><i class="ti ti-chart-donut fs-16 me-2"></i><span>Profit & Loss</span></a></li>
								<li><a href="annual-report.html"><i class="ti ti-report-search fs-16 me-2"></i><span>Annual Report</span></a></li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Content (CMS)</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-page-break fs-16 me-2"></i><span>Pages</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="pages.html">Pages</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-wallpaper fs-16 me-2"></i><span>Blog</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="all-blog.html">All Blog</a></li>
										<li><a href="blog-tag.html">Blog Tags</a></li>
										<li><a href="blog-categories.html">Categories</a></li>
										<li><a href="blog-comments.html">Blog Comments</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-map-pin fs-16 me-2"></i><span>Location</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="countries.html">Countries</a></li>
										<li><a href="states.html">States</a></li>
										<li><a href="cities.html">Cities</a></li>
									</ul>
								</li>
								<li><a href="testimonials.html"><i class="ti ti-star fs-16 me-2"></i><span>Testimonials</span></a></li>
								<li><a href="faq.html"><i class="ti ti-help-circle fs-16 me-2"></i><span>FAQ</span></a></li>

							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">User Management</h6>
							<ul>
								<li><a href="users.html"><i class="ti ti-shield-up fs-16 me-2"></i><span>Users</span></a></li>
								<li><a href="roles-permissions.html"><i class="ti ti-jump-rope fs-16 me-2"></i><span>Roles & Permissions</span></a></li>
								<li><a href="delete-account.html"><i class="ti ti-trash-x fs-16 me-2"></i><span>Delete Account Request</span></a></li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Pages</h6>
							<ul>
								<li><a href="profile.html"><i class="ti ti-user-circle fs-16 me-2"></i><span>Profile</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-shield fs-16 me-2"></i><span>Authentication</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Login<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="signin.html">Cover</a></li>
												<li><a href="signin-2.html">Illustration</a></li>
												<li><a href="signin-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Register<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="register.html">Cover</a></li>
												<li><a href="register-2.html">Illustration</a></li>
												<li><a href="register-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Forgot Password<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="forgot-password.html">Cover</a></li>
												<li><a href="forgot-password-2.html">Illustration</a></li>
												<li><a href="forgot-password-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Reset Password<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="reset-password.html">Cover</a></li>
												<li><a href="reset-password-2.html">Illustration</a></li>
												<li><a href="reset-password-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Email Verification<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="email-verification.html">Cover</a></li>
												<li><a href="email-verification-2.html">Illustration</a></li>
												<li><a href="email-verification-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">2 Step Verification<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="two-step-verification.html">Cover</a></li>
												<li><a href="two-step-verification-2.html">Illustration</a></li>
												<li><a href="two-step-verification-3.html">Basic</a></li>
											</ul>
										</li>
										<li><a href="lock-screen.html">Lock Screen</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-file-x fs-16 me-2"></i><span>Error Pages</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="error-404.html">404 Error </a></li>
										<li><a href="error-500.html">500 Error </a></li>
									</ul>
								</li>
								<li>
									<a href="blank-page.html"><i class="ti ti-file fs-16 me-2"></i><span>Blank Page</span> </a>
								</li>
								<li>
									<a href="pricing.html"><i class="ti ti-currency-dollar fs-16 me-2"></i><span>Pricing</span> </a>
								</li>
								<li>
									<a href="coming-soon.html"><i class="ti ti-send fs-16 me-2"></i><span>Coming Soon</span> </a>
								</li>
								<li>
									<a href="under-maintenance.html"><i class="ti ti-alert-triangle fs-16 me-2"></i><span>Under Maintenance</span> </a>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Settings</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-settings fs-16 me-2"></i><span>General Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="general-settings.html">Profile</a></li>
										<li><a href="security-settings.html">Security</a></li>
										<li><a href="notification.html">Notifications</a></li>
										<li><a href="connected-apps.html">Connected Apps</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-world fs-16 me-2"></i><span>Website Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="system-settings.html">System Settings</a></li>
										<li><a href="company-settings.html">Company Settings </a></li>
										<li><a href="localization-settings.html">Localization</a></li>
										<li><a href="prefixes.html">Prefixes</a></li>
										<li><a href="preference.html">Preference</a></li>
										<li><a href="appearance.html">Appearance</a></li>
										<li><a href="social-authentication.html">Social Authentication</a></li>
										<li><a href="language-settings.html">Language</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-device-mobile fs-16 me-2"></i>
										<span>App Settings</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Invoice<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="invoice-settings.html">Invoice Settings</a></li>
												<li><a href="invoice-templates.html">Invoice Template</a></li>
											</ul>
										</li>
										<li><a href="printer-settings.html">Printer</a></li>
										<li><a href="pos-settings.html">POS</a></li>
										<li><a href="custom-fields.html">Custom Fields</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-device-desktop fs-16 me-2"></i>
										<span>System Settings</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Email<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="email-settings.html">Email Settings</a></li>
												<li><a href="email-templates.html">Email Template</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">SMS<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="sms-settings.html">SMS Settings</a></li>
												<li><a href="sms-templates.html">SMS Template</a></li>
											</ul>
										</li>
										<li><a href="otp-settings.html">OTP</a></li>
										<li><a href="gdpr-settings.html">GDPR Cookies</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-settings-dollar fs-16 me-2"></i>
										<span>Financial Settings</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="payment-gateway-settings.html">Payment Gateway</a></li>
										<li><a href="bank-settings-grid.html">Bank Accounts</a></li>
										<li><a href="tax-rates.html">Tax Rates</a></li>
										<li><a href="currency-settings.html">Currencies</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-settings-2 fs-16 me-2"></i>
										<span>Other Settings</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="storage-settings.html">Storage</a></li>
										<li><a href="ban-ip-address.html">Ban IP Address</a></li>
									</ul>
								</li>
								<li>
									<a href="signin.html"><i class="ti ti-logout fs-16 me-2"></i><span>Logout</span> </a>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">UI Interface</h6>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);">
										<i class="ti ti-vector-bezier fs-16 me-2"></i><span>Base UI</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="ui-alerts.html">Alerts</a></li>
										<li><a href="ui-accordion.html">Accordion</a></li>
										<li><a href="ui-avatar.html">Avatar</a></li>
										<li><a href="ui-badges.html">Badges</a></li>
										<li><a href="ui-buttons.html">Buttons</a></li>
										<li><a href="ui-buttons-group.html">Button Group</a></li>
										<li><a href="ui-breadcrumb.html">Breadcrumb</a></li>
										<li><a href="ui-cards.html">Card</a></li>
										<li><a href="ui-carousel.html">Carousel</a></li>
										<li><a href="ui-collapse.html">Collapse</a></li>
										<li><a href="ui-dropdowns.html">Dropdowns</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/retail-pos/html/ui-ratio">Ratio</a></li>
										<li><a href="ui-grid.html">Grid</a></li>
										<li><a href="ui-images.html">Images</a></li>
										<li><a href="ui-links.html">Links</a></li>
										<li><a href="ui-list-group.html">List Group</a></li>
										<li><a href="ui-modals.html">Modals</a></li>
										<li><a href="ui-offcanvas.html">Offcanvas</a></li>
										<li><a href="ui-pagination.html">Pagination</a></li>
										<li><a href="ui-popovers.html">Popovers</a></li>
										<li><a href="ui-progress.html">Progress</a></li>
										<li><a href="ui-placeholders.html">Placeholders</a></li>
										<li><a href="ui-spinner.html">Spinner</a></li>
										<li><a href="ui-nav-tabs.html">Tabs</a></li>
										<li><a href="ui-toasts.html">Toasts</a></li>
										<li><a href="ui-tooltips.html">Tooltips</a></li>
										<li><a href="ui-typography.html">Typography</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);">
										<i class="ti ti-stack-2 fs-16 me-2"></i><span>Advanced UI</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="ui-clipboard.html">Clipboard</a></li>
										<li><a href="ui-drag-drop.html">Drag & Drop</a></li>
										<li><a href="ui-lightbox.html">Lightbox</a></li>
										<li><a href="ui-scrollbar.html">Scrollbar</a></li>
										<li><a href="ui-sweetalerts.html">Sweet Alerts</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-chart-infographic fs-16 me-2"></i>
										<span>Charts</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="chart-apex.html">Apex Charts</a></li>
										<li><a href="chart-c3.html">Chart C3</a></li>
										<li><a href="chart-js.html">Chart Js</a></li>
										<li><a href="chart-morris.html">Morris Charts</a></li>
										<li><a href="chart-flot.html">Flot Charts</a></li>
										<li><a href="chart-peity.html">Peity Charts</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-icons fs-16 me-2"></i>
										<span>Icons</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li><a href="icon-fontawesome.html">Fontawesome Icons</a></li>
										<li><a href="icon-feather.html">Feather Icons</a></li>
										<li><a href="icon-ionic.html">Ionic Icons</a></li>
										<li><a href="icon-material.html">Material Icons</a></li>
										<li><a href="icon-pe7.html">Pe7 Icons</a></li>
										<li><a href="icon-simpleline.html">Simpleline Icons</a></li>
										<li><a href="icon-themify.html">Themify Icons</a></li>
										<li><a href="icon-weather.html">Weather Icons</a></li>
										<li><a href="icon-typicon.html">Typicon Icons</a></li>
										<li><a href="icon-flag.html">Flag Icons</a></li>
										<li><a href="icon-tabler.html">Tabler Icons</a></li>
										<li><a href="icon-bootstrap.html">Bootstrap Icons</a></li>
										<li><a href="icon-remix.html">Remix Icons</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);">
										<i class="ti ti-input-search fs-16 me-2"></i><span>Forms</span><span class="menu-arrow"></span>
									</a>
									<ul>
										<li class="submenu submenu-two">
											<a href="javascript:void(0);">Form Elements<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="form-basic-inputs.html">Basic Inputs</a></li>
												<li><a href="form-checkbox-radios.html">Checkbox & Radios</a></li>
												<li><a href="form-input-groups.html">Input Groups</a></li>
												<li><a href="form-grid-gutters.html">Grid & Gutters</a></li>
												<li><a href="form-select.html">Form Select</a></li>
												<li><a href="form-mask.html">Input Masks</a></li>
												<li><a href="form-fileupload.html">File Uploads</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two">
											<a href="javascript:void(0);">Layouts<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="form-horizontal.html">Horizontal Form</a></li>
												<li><a href="form-vertical.html">Vertical Form</a></li>
												<li><a href="form-floating-labels.html">Floating Labels</a></li>
											</ul>
										</li>
										<li><a href="form-validation.html">Form Validation</a></li>
										<li><a href="form-select2.html">Select2</a></li>
										<li><a href="form-wizard.html">Form Wizard</a></li>
										<li><a href="form-pickers.html">Form Picker</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-table fs-16 me-2"></i><span>Tables</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="tables-basic.html">Basic Tables </a></li>
										<li><a href="data-tables.html">Data Table </a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu-open">
							<h6 class="submenu-hdr">Help</h6>
							<ul>
								<li><a href="https://dreamspos.dreamstechnologies.com/documentation/html.html" target="_blank"><i class="ti ti-file-text fs-16 me-2"></i><span>Documentation</span></a></li>
								<li><a href="https://dreamspos.dreamstechnologies.com/documentation/changelog.html" target="_blank"><i class="ti ti-exchange fs-16 me-2"></i><span>Changelog </span><span class="badge bg-primary badge-xs text-white fs-10 ms-2">v2.3.5</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><i class="ti ti-menu-2 fs-16 me-2"></i><span>Multi Level</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="javascript:void(0);">Level 1.1</a></li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Level 1.2<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="javascript:void(0);">Level 2.1</a></li>
												<li class="submenu submenu-two submenu-three"><a href="javascript:void(0);">Level 2.2<span class="menu-arrow inside-submenu inside-submenu-two"></span></a>
													<ul>
														<li><a href="javascript:void(0);">Level 3.1</a></li>
														<li><a href="javascript:void(0);">Level 3.2</a></li>
													</ul>
												</li>
											</ul>
										</li>
									</ul>
								</li>
							</ul>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<!-- /Sidebar -->

		<!-- Horizontal Sidebar -->
		<div class="sidebar sidebar-horizontal d-none" id="horizontal-menu">
			<div id="sidebar-menu-3" class="sidebar-menu">
				<div class="main-menu">
					<ul class="nav-menu">
						<li class="submenu">
							<a href="index.html"><i class="ti ti-layout-grid fs-16 me-2"></i><span> Main Menu</span> <span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);" class="active subdrop"><span>Dashboard</span> <span class="menu-arrow"></span></a>
									<ul>
										<li><a href="index.html" class="active">Admin Dashboard</a></li>
										<li><a href="admin-dashboard.html">Admin Dashboard 2</a></li>
										<li><a href="sales-dashboard.html">Sales Dashboard</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Super Admin</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="dashboard.html">Dashboard</a></li>
										<li><a href="companies.html">Companies</a></li>
										<li><a href="subscription.html">Subscriptions</a></li>
										<li><a href="packages.html">Packages</a></li>
										<li><a href="domain.html">Domain</a></li>
										<li><a href="purchase-transaction.html">Purchase Transaction</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Application</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="chat.html">Chat</a></li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Call<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="video-call.html">Video Call</a></li>
												<li><a href="audio-call.html">Audio Call</a></li>
												<li><a href="call-history.html">Call History</a></li>
											</ul>
										</li>
										<li><a href="calendar.html">Calendar</a></li>
										<li><a href="contacts.html">Contacts</a></li>
										<li><a href="email.html">Email</a></li>
										<li><a href="todo.html">To Do</a></li>
										<li><a href="notes.html">Notes</a></li>
										<li><a href="file-manager.html">File Manager</a></li>
										<li><a href="projects.html">Projects</a></li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Ecommerce<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="products.html">Products</a></li>
												<li><a href="orders.html">Orders</a></li>
												<li><a href="customers.html">Customers</a></li>
												<li><a href="cart.html">Cart</a></li>
												<li><a href="checkout.html">Checkout</a></li>
												<li><a href="wishlist.html">Wishlist</a></li>
												<li><a href="reviews.html">Reviews</a></li>
											</ul>
										</li>
										<li><a href="social-feed.html">Social Feed</a></li>
										<li><a href="search-list.html">Search List</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-layout-sidebar-right-collapse fs-16 me-2"></i><span>Layouts</span><span class="menu-arrow"></span></a>
							<ul>
								<li><a href="layout-horizontal.html">Horizontal</a></li>
								<li><a href="layout-detached.html">Detached</a></li>
								<li><a href="layout-two-column.html">Two Column</a></li>
								<li><a href="layout-hovered.html">Hovered</a></li>
								<li><a href="layout-boxed.html">Boxed</a></li>
								<li><a href="layout-rtl.html">RTL</a></li>
								<li><a href="layout-dark.html">Dark</a></li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-brand-unity fs-16 me-2"></i><span> Inventory
								</span> <span class="menu-arrow"></span></a>
							<ul>
								<li><a href="product-list.html"><span>Products</span></a></li>
								<li><a href="add-product.html"><span>Create Product</span></a></li>
								<li><a href="expired-products.html"><span>Expired Products</span></a></li>
								<li><a href="low-stocks.html"><span>Low Stocks</span></a></li>
								<li><a href="category-list.html"><span>Category</span></a></li>
								<li><a href="sub-categories.html"><span>Sub Category</span></a></li>
								<li><a href="brand-list.html"><span>Brands</span></a></li>
								<li><a href="units.html"><span>Units</span></a></li>
								<li><a href="varriant-attributes.html"><span>Variant Attributes</span></a></li>
								<li><a href="warranty.html"><span>Warranties</span></a></li>
								<li><a href="barcode.html"><span>Print Barcode</span></a></li>
								<li><a href="qrcode.html"><span>Print QR Code</span></a></li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-layout-grid fs-16 me-2"></i><span>Sales &amp; Purchase</span> <span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Stock</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="manage-stocks.html"><span>Manage Stock</span></a></li>
										<li><a href="stock-adjustment.html"><span>Stock Adjustment</span></a></li>
										<li><a href="stock-transfer.html"><span>Stock Transfer</span></a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Sales</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Sales</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="online-orders.html">Online Orders</a></li>
												<li><a href="pos-orders.html">POS Orders</a></li>
											</ul>
										</li>
										<li><a href="invoice.html"><span>Invoices</span></a></li>
										<li><a href="sales-returns.html"><span>Sales Return</span></a></li>
										<li><a href="quotation-list.html"><span>Quotation</span></a></li>
										<li class="submenu">
											<a href="javascript:void(0);"><span>POS</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="pos.html">POS 1</a></li>
												<li><a href="pos-2.html">POS 2</a></li>
												<li><a href="pos-3.html">POS 3</a></li>
												<li><a href="pos-4.html">POS 4</a></li>
												<li><a href="pos-5.html">POS 5</a></li>
												<li><a href="https://dreamspos.dreamstechnologies.com/restaurant-pos/html/index.html" target="_blank">POS 6</a></li>
												<li><a href="https://dreamspos.dreamstechnologies.com/laundry-pos/html/template/index.html" target="_blank">POS 7</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/pharmacy-pos/html/index.html" target="_blank">POS 8</a></li>
											</ul>
										</li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Promo</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="coupons.html"><span>Coupons</span></a></li>
										<li><a href="gift-cards.html"><span>Gift Cards</span></a></li>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Discount</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="discount-plan.html">Discount Plan</a></li>
												<li><a href="discount.html">Discount</a></li>
											</ul>
										</li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Purchase</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="purchase-list.html"><span>Purchases</span></a></li>
										<li><a href="purchase-order-report.html"><span>Purchase Order</span></a></li>
										<li><a href="purchase-returns.html"><span>Purchase Return</span></a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Expenses</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="expense-list.html">Expenses</a></li>
										<li><a href="expense-category.html">Expense Category</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Income</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="income.html">Income</a></li>
										<li><a href="income-category.html">Income Category</a></li>
									</ul>
								</li>
								<li><a href="account-list.html"><span>Bank Accounts</span></a></li>
								<li><a href="money-transfer.html"><span>Money Transfer</span></a></li>
								<li><a href="balance-sheet.html"><span>Balance Sheet</span></a></li>
								<li><a href="trial-balance.html"><span>Trial Balance</span></a></li>
								<li><a href="cash-flow.html"><span>Cash Flow</span></a></li>
								<li><a href="account-statement.html"><span>Account Statement</span></a></li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-users-group fs-16 me-2"></i><span>UI Interface</span> <span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Base UI</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="ui-alerts.html">Alerts</a></li>
										<li><a href="ui-accordion.html">Accordion</a></li>
										<li><a href="ui-avatar.html">Avatar</a></li>
										<li><a href="ui-badges.html">Badges</a></li>
										<li><a href="ui-buttons.html">Buttons</a></li>
										<li><a href="ui-buttons-group.html">Button Group</a></li>
										<li><a href="ui-breadcrumb.html">Breadcrumb</a></li>
										<li><a href="ui-cards.html">Card</a></li>
										<li><a href="ui-carousel.html">Carousel</a></li>
										<li><a href="ui-collapse.html">Collapse</a></li>
										<li><a href="ui-dropdowns.html">Dropdowns</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/retail-pos/html/ui-ratio">Ratio</a></li>
										<li><a href="ui-grid.html">Grid</a></li>
										<li><a href="ui-images.html">Images</a></li>
										<li><a href="ui-links.html">Links</a></li>
										<li><a href="ui-list-group.html">List Group</a></li>
										<li><a href="ui-modals.html">Modals</a></li>
										<li><a href="ui-offcanvas.html">Offcanvas</a></li>
										<li><a href="ui-pagination.html">Pagination</a></li>
										<li><a href="ui-popovers.html">Popovers</a></li>
										<li><a href="ui-progress.html">Progress</a></li>
										<li><a href="ui-placeholders.html">Placeholders</a></li>
										<li><a href="ui-spinner.html">Spinner</a></li>
										<li><a href="ui-nav-tabs.html">Tabs</a></li>
										<li><a href="ui-toasts.html">Toasts</a></li>
										<li><a href="ui-tooltips.html">Tooltips</a></li>
										<li><a href="ui-typography.html">Typography</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Advanced UI</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="ui-clipboard.html">Clipboard</a></li>
										<li><a href="ui-drag-drop.html">Drag & Drop</a></li>
										<li><a href="ui-lightbox.html">Lightbox</a></li>
										<li><a href="ui-scrollbar.html">Scrollbar</a></li>
										<li><a href="ui-sweetalerts.html">Sweet Alerts</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Charts</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="chart-apex.html">Apex Charts</a></li>
										<li><a href="chart-c3.html">Chart C3</a></li>
										<li><a href="chart-js.html">Chart Js</a></li>
										<li><a href="chart-morris.html">Morris Charts</a></li>
										<li><a href="chart-flot.html">Flot Charts</a></li>
										<li><a href="chart-peity.html">Peity Charts</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Icons</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="icon-fontawesome.html">Fontawesome Icons</a></li>
										<li><a href="icon-feather.html">Feather Icons</a></li>
										<li><a href="icon-ionic.html">Ionic Icons</a></li>
										<li><a href="icon-material.html">Material Icons</a></li>
										<li><a href="icon-pe7.html">Pe7 Icons</a></li>
										<li><a href="icon-simpleline.html">Simpleline Icons</a></li>
										<li><a href="icon-themify.html">Themify Icons</a></li>
										<li><a href="icon-weather.html">Weather Icons</a></li>
										<li><a href="icon-typicon.html">Typicon Icons</a></li>
										<li><a href="icon-flag.html">Flag Icons</a></li>
										<li><a href="icon-tabler.html">Tabler Icons</a></li>
										<li><a href="icon-bootstrap.html">Bootstrap Icons</a></li>
										<li><a href="icon-remix.html">Remix Icons</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span> Forms</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu submenu-two">
											<a href="javascript:void(0);"><span>Form Elements</span><span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="form-basic-inputs.html">Basic Inputs</a></li>
												<li><a href="form-checkbox-radios.html">Checkbox & Radios</a></li>
												<li><a href="form-input-groups.html">Input Groups</a></li>
												<li><a href="form-grid-gutters.html">Grid & Gutters</a></li>
												<li><a href="form-select.html">Form Select</a></li>
												<li><a href="form-mask.html">Input Masks</a></li>
												<li><a href="form-fileupload.html">File Uploads</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two">
											<a href="javascript:void(0);"><span> Layouts</span><span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="form-horizontal.html">Horizontal Form</a></li>
												<li><a href="form-vertical.html">Vertical Form</a></li>
												<li><a href="form-floating-labels.html">Floating Labels</a></li>
											</ul>
										</li>
										<li><a href="form-validation.html">Form Validation</a></li>
										<li><a href="form-select2.html">Select2</a></li>
										<li><a href="form-wizard.html">Form Wizard</a></li>
										<li><a href="form-pickers.html">Form Picker</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Tables</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="tables-basic.html">Basic Tables </a></li>
										<li><a href="data-tables.html">Data Table </a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-page-break fs-16 me-2"></i><span>Pages</span> <span class="menu-arrow"></span></a>
							<ul>
								<li><a href="profile.html"><span>Profile</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Authentication</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Login<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="signin.html">Cover</a></li>
												<li><a href="signin-2.html">Illustration</a></li>
												<li><a href="signin-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Register<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="register.html">Cover</a></li>
												<li><a href="register-2.html">Illustration</a></li>
												<li><a href="register-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Forgot Password<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="forgot-password.html">Cover</a></li>
												<li><a href="forgot-password-2.html">Illustration</a></li>
												<li><a href="forgot-password-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Reset Password<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="reset-password.html">Cover</a></li>
												<li><a href="reset-password-2.html">Illustration</a></li>
												<li><a href="reset-password-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Email Verification<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="email-verification.html">Cover</a></li>
												<li><a href="email-verification-2.html">Illustration</a></li>
												<li><a href="email-verification-3.html">Basic</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">2 Step Verification<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="two-step-verification.html">Cover</a></li>
												<li><a href="two-step-verification-2.html">Illustration</a></li>
												<li><a href="two-step-verification-3.html">Basic</a></li>
											</ul>
										</li>
										<li><a href="lock-screen.html">Lock Screen</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Error</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="error-404.html">404 Error </a></li>
										<li><a href="error-500.html">500 Error </a></li>
									</ul>
								</li>
								<li><a href="blank-page.html"><span>Blank Page</span> </a></li>
								<li><a href="pricing.html"><span>Pricing</span> </a></li>
								<li><a href="coming-soon.html"><span>Coming Soon</span> </a></li>
								<li><a href="under-maintenance.html"><span>Under Maintenance</span> </a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Content</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Pages</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="pages.html">Pages</a></li>
											</ul>
										</li>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Blog</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="all-blog.html">All Blog</a></li>
												<li><a href="blog-tag.html">Blog Tags</a></li>
												<li><a href="blog-categories.html">Categories</a></li>
												<li><a href="blog-comments.html">Blog Comments</a></li>
											</ul>
										</li>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Location</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="countries.html">Countries</a></li>
												<li><a href="states.html">States</a></li>
												<li><a href="cities.html">Cities</a></li>
											</ul>
										</li>
										<li><a href="testimonials.html"><span>Testimonials</span></a></li>
										<li><a href="faq.html"><span>FAQ</span></a></li>
		
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Employees</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="employees-grid.html"><span>Employees</span></a></li>
										<li><a href="department-grid.html"><span>Departments</span></a></li>
										<li><a href="designation.html"><span>Designation</span></a></li>
										<li><a href="shift.html"><span>Shifts</span></a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Attendence</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="attendance-employee.html">Employee Attendence</a></li>
										<li><a href="attendance-admin.html">Admin Attendence</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Leaves &amp; Holidays</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="leaves-admin.html">Admin Leaves</a></li>
										<li><a href="leaves-employee.html">Employee Leaves</a></li>
										<li><a href="leave-types.html">Leave Types</a></li>
										<li><a href="holidays.html"><span>Holidays</span></a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="employee-salary.html"><span>Payroll</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="employee-salary.html">Employee Salary</a></li>
										<li><a href="payslip.html">Payslip</a></li>
									</ul>
								</li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-chart-bar fs-16 me-2"></i><span>Reports</span><span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Sales Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="sales-report.html">Sales Report</a></li>
										<li><a href="best-seller.html">Best Seller</a></li>
									</ul>
								</li>
								<li><a href="purchase-report.html"><span>Purchase report</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Inventory Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="inventory-report.html">Inventory Report</a></li>
										<li><a href="stock-history.html">Stock History</a></li>
										<li><a href="sold-stock.html">Sold Stock</a></li>
									</ul>
								</li>
								<li><a href="invoice-report.html"><span>Invoice Report</span></a></li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Supplier Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="supplier-report.html">Supplier Report</a></li>
										<li><a href="supplier-due-report.html">Supplier Due Report</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Customer Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="customer-report.html">Customer Report</a></li>
										<li><a href="customer-due-report.html">Customer Due Report</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Product Report</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="product-report.html">Product Report</a></li>
										<li><a href="product-expiry-report.html">Product Expiry Report</a></li>
										<li><a href="product-quantity-alert.html">Product Quantity Alert</a></li>
									</ul>
								</li>
								<li><a href="expense-report.html"><span>Expense Report</span></a></li>
								<li><a href="income-report.html"><span>Income Report</span></a></li>
								<li><a href="tax-reports.html"><span>Tax Report</span></a></li>
								<li><a href="profit-and-loss.html"><span>Profit & Loss</span></a></li>
								<li><a href="annual-report.html"><span>Annual Report</span></a></li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-settings fs-16 me-2"></i><span>Settings</span><span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><span>General Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="general-settings.html">Profile</a></li>
										<li><a href="security-settings.html">Security</a></li>
										<li><a href="notification.html">Notifications</a></li>
										<li><a href="connected-apps.html">Connected Apps</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Website Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="system-settings.html">System Settings</a></li>
										<li><a href="company-settings.html">Company Settings </a></li>
										<li><a href="localization-settings.html">Localization</a></li>
										<li><a href="prefixes.html">Prefixes</a></li>
										<li><a href="preference.html">Preference</a></li>
										<li><a href="appearance.html">Appearance</a></li>
										<li><a href="social-authentication.html">Social Authentication</a></li>
										<li><a href="language-settings.html">Language</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>App Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Invoice<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="invoice-settings.html">Invoice Settings</a></li>
												<li><a href="invoice-templates.html">Invoice Template</a></li>
											</ul>
										</li>
										<li><a href="printer-settings.html">Printer</a></li>
										<li><a href="pos-settings.html">POS</a></li>
										<li><a href="custom-fields.html">Custom Fields</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>System Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li class="submenu submenu-two"><a href="javascript:void(0);">Email<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="email-settings.html">Email Settings</a></li>
												<li><a href="email-templates.html">Email Template</a></li>
											</ul>
										</li>
										<li class="submenu submenu-two"><a href="javascript:void(0);">SMS<span class="menu-arrow inside-submenu"></span></a>
											<ul>
												<li><a href="sms-settings.html">SMS Settings</a></li>
												<li><a href="sms-templates.html">SMS Template</a></li>
											</ul>
										</li>
										<li><a href="otp-settings.html">OTP</a></li>
										<li><a href="gdpr-settings.html">GDPR Cookies</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Financial Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="payment-gateway-settings.html">Payment Gateway</a></li>
										<li><a href="bank-settings-grid.html">Bank Accounts</a></li>
										<li><a href="tax-rates.html">Tax Rates</a></li>
										<li><a href="currency-settings.html">Currencies</a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Other Settings</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="storage-settings.html">Storage</a></li>
										<li><a href="ban-ip-address.html">Ban IP Address</a></li>
									</ul>
								</li>
								<li>
									<a href="signin.html"><span>Logout</span> </a>
								</li>
							</ul>
						</li>
						<li class="submenu">
							<a href="javascript:void(0);"><i class="ti ti-circle-plus fs-16 me-2"></i><span>More</span><span class="menu-arrow"></span></a>
							<ul>
								<li class="submenu">
									<a href="javascript:void(0);"><span>People</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="customers.html"><span>Customers</span></a></li>
										<li><a href="billers.html"><span>Billers</span></a></li>
										<li><a href="suppliers.html"><span>Suppliers</span></a></li>
										<li><a href="store-list.html"><span>Stores</span></a></li>
										<li><a href="warehouse.html"><span>Warehouses</span></a>
										</li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>User Management</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="users.html"><span>Users</span></a></li>
										<li><a href="roles-permissions.html"><span>Roles & Permissions</span></a></li>
										<li><a href="delete-account.html"><span>Delete Account Request</span></a></li>
									</ul>
								</li>
								<li class="submenu">
									<a href="javascript:void(0);"><span>Help</span><span class="menu-arrow"></span></a>
									<ul>
										<li><a href="https://dreamspos.dreamstechnologies.com/documentation/html.html" target="_blank"><span>Documentation</span></a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/documentation/changelog.html" target="_blank"><span>Changelog v2.3.5</span></a></li>
										<li class="submenu">
											<a href="javascript:void(0);"><span>Multi Level</span><span class="menu-arrow"></span></a>
											<ul>
												<li><a href="javascript:void(0);">Level 1.1</a></li>
												<li class="submenu submenu-two"><a href="javascript:void(0);">Level 1.2<span class="menu-arrow inside-submenu"></span></a>
													<ul>
														<li><a href="javascript:void(0);">Level 2.1</a></li>
														<li class="submenu submenu-two submenu-three"><a href="javascript:void(0);">Level 2.2<span class="menu-arrow inside-submenu inside-submenu-two"></span></a>
															<ul>
																<li><a href="javascript:void(0);">Level 3.1</a></li>
																<li><a href="javascript:void(0);">Level 3.2</a></li>
															</ul>
														</li>
													</ul>
												</li>
											</ul>
										</li>
									</ul>
								</li>
							</ul>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<!-- /Horizontal Sidebar -->

		<!-- Two Col Sidebar -->
		<div class="two-col-sidebar d-none" id="two-col-sidebar">
			<div class="sidebar sidebar-twocol">
				<div class="twocol-mini">
					<div class="sidebar-left slimscroll">
						<div class="nav flex-column align-items-center nav-pills" id="sidebar-tabs" role="tablist"
							aria-orientation="vertical">
							<a href="pos.html#" class="nav-link active" title="Dashboard" data-bs-toggle="tab" data-bs-target="#dashboard">
								<i class="ti ti-smart-home"></i>
							</a>
							<a href="pos.html#" class="nav-link " title="Super Admin" data-bs-toggle="tab" data-bs-target="#super-admin">
								<i class="ti ti-user-star"></i>
							</a>
							<a href="pos.html#" class="nav-link " title="Apps" data-bs-toggle="tab" data-bs-target="#application">
								<i class="ti ti-layout-grid-add"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Layout" data-bs-toggle="tab" data-bs-target="#layout">
								<i class="ti ti-layout-board-split"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Inventory" data-bs-toggle="tab" data-bs-target="#inventory">
								<i class="ti ti-table-plus"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Stock" data-bs-toggle="tab" data-bs-target="#stock">
								<i class="ti ti-stack-3"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Sales" data-bs-toggle="tab" data-bs-target="#sales">
								<i class="ti ti-device-laptop"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Finance" data-bs-toggle="tab" data-bs-target="#finance">
								<i class="ti ti-shopping-cart-dollar"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Hrm" data-bs-toggle="tab" data-bs-target="#hrm">
								<i class="ti ti-cash"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Reports" data-bs-toggle="tab" data-bs-target="#reports">
								<i class="ti ti-license"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Pages" data-bs-toggle="tab" data-bs-target="#pages">
								<i class="ti ti-page-break"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Settings" data-bs-toggle="tab" data-bs-target="#settings">
								<i class="ti ti-lock-check"></i>
							</a>
							<a href="pos.html#" class="nav-link " title="UI Elements" data-bs-toggle="tab" data-bs-target="#ui-elements">
								<i class="ti ti-ux-circle"></i>
							</a>
							<a href="pos.html#" class="nav-link" title="Extras" data-bs-toggle="tab" data-bs-target="#extras">
								<i class="ti ti-vector-triangle"></i>
							</a>
						</div>
					</div>
				</div>
				<div class="sidebar-right">
					<!-- Logo -->
					<div class="sidebar-logo">
						<a href="index.html" class="logo logo-normal">
							<img src="{{ asset('assets/img/logo.svg') }}" alt="Img">
						</a>
						<a href="index.html" class="logo logo-white">
							<img src="{{ asset('assets/img/logo-white.svg') }}" alt="Img">
						</a>
						<a href="index.html" class="logo-small">
							<img src="{{ asset('assets/img/logo-small.png') }}" alt="Img">
						</a>
						<a href="index.html" class="logo-small-white">
							<img src="{{ asset('assets/img/logo-small-white.png') }}" alt="Img">
						</a>
					</div>
					<!-- /Logo -->
					<div class="sidebar-scroll">
						<div class="text-center rounded bg-light p-3 mb-3 border">
							<div class="avatar avatar-lg online mb-3">
								<img src="{{ asset('assets/img/customer/customer15.jpg') }}" alt="Img" class="img-fluid rounded-circle">
							</div>
							<h6 class="fs-14 fw-bold mb-1">Adrian Herman</h6>
							<p class="fs-12 mb-0">System Admin</p>
						</div>
						<div class="tab-content" id="v-pills-tabContent">
							<div class="tab-pane fade show active" id="dashboard">
								<ul>
									<li class="menu-title"><span>MAIN</span></li>
									<li><a href="index.html" class="active">Admin Dashboard</a></li>
									<li><a href="admin-dashboard.html">Admin Dashboard 2</a></li>
									<li><a href="sales-dashboard.html">Sales Dashboard</a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="super-admin">
								<ul>
									<li class="menu-title"><span>SUPER ADMIN</span></li>
									<li><a href="dashboard.html">Dashboard</a></li>
									<li><a href="companies.html">Companies</a></li>
									<li><a href="subscription.html">Subscriptions</a></li>
									<li><a href="packages.html">Packages</a></li>
									<li><a href="domain.html">Domain</a></li>
									<li><a href="purchase-transaction.html">Purchase Transaction</a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="application">
								<ul>
									<li><a href="chat.html">Chat</a></li>
									<li class="submenu submenu-two"><a href="javascript:void(0);">Call<span class="menu-arrow inside-submenu"></span></a>
										<ul>
											<li><a href="video-call.html">Video Call</a></li>
											<li><a href="audio-call.html">Audio Call</a></li>
											<li><a href="call-history.html">Call History</a></li>
										</ul>
									</li>
									<li><a href="calendar.html">Calendar</a></li>
									<li><a href="contacts.html">Contacts</a></li>
									<li><a href="email.html">Email</a></li>
									<li><a href="todo.html">To Do</a></li>
									<li><a href="notes.html">Notes</a></li>
									<li><a href="file-manager.html">File Manager</a></li>
									<li><a href="projects.html">Projects</a></li>
									<li class="submenu submenu-two"><a href="javascript:void(0);">Ecommerce<span class="menu-arrow inside-submenu"></span></a>
										<ul>
											<li><a href="products.html">Products</a></li>
											<li><a href="orders.html">Orders</a></li>
											<li><a href="customers.html">Customers</a></li>
											<li><a href="cart.html">Cart</a></li>
											<li><a href="checkout.html">Checkout</a></li>
											<li><a href="wishlist.html">Wishlist</a></li>
											<li><a href="reviews.html">Reviews</a></li>
										</ul>
									</li>
									<li><a href="social-feed.html">Social Feed</a></li>
									<li><a href="search-list.html">Search List</a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="layout">
								<ul>
									<li class="menu-title"><span>LAYOUT</span></li>
									<li><a href="layout-horizontal.html">Horizontal</a></li>
									<li><a href="layout-detached.html">Detached</a></li>
									<li><a href="layout-two-column.html">Two Column</a></li>
									<li><a href="layout-hovered.html">Hovered</a></li>
									<li><a href="layout-boxed.html">Boxed</a></li>
									<li><a href="layout-rtl.html">RTL</a></li>
									<li><a href="layout-dark.html">Dark</a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="inventory">
								<ul>
									<li class="menu-title"><span>Inventory</span></li>
									<li><a href="product-list.html"><span>Products</span></a></li>
									<li><a href="add-product.html"><span>Create Product</span></a></li>
									<li><a href="expired-products.html"><span>Expired Products</span></a></li>
									<li><a href="low-stocks.html"><span>Low Stocks</span></a></li>
									<li><a href="category-list.html"><span>Category</span></a></li>
									<li><a href="sub-categories.html"><span>Sub Category</span></a></li>
									<li><a href="brand-list.html"><span>Brands</span></a></li>
									<li><a href="units.html"><span>Units</span></a></li>
									<li><a href="varriant-attributes.html"><span>Variant Attributes</span></a></li>
									<li><a href="warranty.html"><span>Warranties</span></a></li>
									<li><a href="barcode.html"><span>Print Barcode</span></a></li>
									<li><a href="qrcode.html"><span>Print QR Code</span></a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="stock">
								<ul>
									<li class="menu-title"><span>Stock</span></li>
									<li><a href="manage-stocks.html"><span>Manage Stock</span></a></li>
									<li><a href="stock-adjustment.html"><span>Stock Adjustment</span></a></li>
									<li><a href="stock-transfer.html"><span>Stock Transfer</span></a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="sales">
								<ul>
									<li class="menu-title"><span>Sales</span></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Sales</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="online-orders.html">Online Orders</a></li>
											<li><a href="pos-orders.html">POS Orders</a></li>
										</ul>
									</li>
									<li><a href="invoice.html"><span>Invoices</span></a></li>
									<li><a href="sales-returns.html"><span>Sales Return</span></a></li>
									<li><a href="quotation-list.html"><span>Quotation</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>POS</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="pos.html">POS 1</a></li>
											<li><a href="pos-2.html">POS 2</a></li>
											<li><a href="pos-3.html">POS 3</a></li>
											<li><a href="pos-4.html">POS 4</a></li>
											<li><a href="pos-5.html">POS 5</a></li>
											<li><a href="https://dreamspos.dreamstechnologies.com/restaurant-pos/html/index.html" target="_blank">POS 6</a></li>
											<li><a href="https://dreamspos.dreamstechnologies.com/laundry-pos/html/template/index.html" target="_blank">POS 7</a></li>
										<li><a href="https://dreamspos.dreamstechnologies.com/pharmacy-pos/html/index.html" target="_blank">POS 8</a></li>
										</ul>
									</li>
								</ul>
							</div>
							<div class="tab-pane fade" id="finance">
								<ul>
									<li class="menu-title"><span>FINANCE & ACCOUNTS</span></li>
									<li><a href="coupons.html"><span>Coupons</span></a></li>
									<li><a href="gift-cards.html"><span>Gift Cards</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Discount</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="discount-plan.html">Discount Plan</a></li>
											<li><a href="discount.html">Discount</a></li>
										</ul>
									</li>
									<li><a href="purchase-list.html"><span>Purchases</span></a></li>
									<li><a href="purchase-order-report.html"><span>Purchase Order</span></a></li>
									<li><a href="purchase-returns.html"><span>Purchase Return</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Expenses</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="expense-list.html">Expenses</a></li>
											<li><a href="expense-category.html">Expense Category</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Income</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="income.html">Income</a></li>
											<li><a href="income-category.html">Income Category</a></li>
										</ul>
									</li>
									<li><a href="account-list.html"><span>Bank Accounts</span></a></li>
									<li><a href="money-transfer.html"><span>Money Transfer</span></a></li>
									<li><a href="balance-sheet.html"><span>Balance Sheet</span></a></li>
									<li><a href="trial-balance.html"><span>Trial Balance</span></a></li>
									<li><a href="cash-flow.html"><span>Cash Flow</span></a></li>
									<li><a href="account-statement.html"><span>Account Statement</span></a></li>
									<li><a href="customers.html"><span>Customers</span></a></li>
									<li><a href="billers.html"><span>Billers</span></a></li>
									<li><a href="suppliers.html"><span>Suppliers</span></a></li>
									<li><a href="store-list.html"><span>Stores</span></a></li>
									<li><a href="warehouse.html"><span>Warehouses</span></a></li>							
								</ul>
							</div>
							<div class="tab-pane fade" id="hrm">
								<ul>
									<li class="menu-title"><span>Hrm</span></li>
									<li><a href="employees-grid.html"><span>Employees</span></a></li>
									<li><a href="department-grid.html"><span>Departments</span></a></li>
									<li><a href="designation.html"><span>Designation</span></a></li>
									<li><a href="shift.html"><span>Shifts</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Attendence</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="attendance-employee.html">Employee</a></li>
											<li><a href="attendance-admin.html">Admin</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Leaves</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="leaves-admin.html">Admin Leaves</a></li>
											<li><a href="leaves-employee.html">Employee Leaves</a></li>
											<li><a href="leave-types.html">Leave Types</a></li>
										</ul>
									</li>
									<li><a href="holidays.html"><span>Holidays</span></a>
									</li>
									<li class="submenu">
										<a href="employee-salary.html"><span>Payroll</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="employee-salary.html">Employee Salary</a></li>
											<li><a href="payslip.html">Payslip</a></li>
										</ul>
									</li>
								</ul>
							</div>
							<div class="tab-pane fade" id="reports">
								<ul>
									<li class="menu-title"><span>Reports</span></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Sales Report</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="sales-report.html">Sales Report</a></li>
											<li><a href="best-seller.html">Best Seller</a></li>
										</ul>
									</li>
									<li><a href="purchase-report.html"><span>Purchase report</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Inventory Report</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="inventory-report.html">Inventory Report</a></li>
											<li><a href="stock-history.html">Stock History</a></li>
											<li><a href="sold-stock.html">Sold Stock</a></li>
										</ul>
									</li>
									<li><a href="invoice-report.html"><span>Invoice Report</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Supplier Report</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="supplier-report.html">Supplier Report</a></li>
											<li><a href="supplier-due-report.html">Supplier Due Report</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Customer Report</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="customer-report.html">Customer Report</a></li>
											<li><a href="customer-due-report.html">Customer Due Report</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Product Report</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="product-report.html">Product Report</a></li>
											<li><a href="product-expiry-report.html">Product Expiry Report</a></li>
											<li><a href="product-quantity-alert.html">Product Quantity Alert</a></li>
										</ul>
									</li>
									<li><a href="expense-report.html"><span>Expense Report</span></a></li>
									<li><a href="income-report.html"><span>Income Report</span></a></li>
									<li><a href="tax-reports.html"><span>Tax Report</span></a></li>
									<li><a href="profit-and-loss.html"><span>Profit & Loss</span></a></li>
									<li><a href="annual-report.html"><span>Annual Report</span></a></li>
								</ul>
							</div>
							<div class="tab-pane fade" id="pages">
								<ul>
									<li class="menu-title"><span>Pages</span></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Pages</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="pages.html">Pages</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Blog</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="all-blog.html">All Blog</a></li>
											<li><a href="blog-tag.html">Blog Tags</a></li>
											<li><a href="blog-categories.html">Categories</a></li>
											<li><a href="blog-comments.html">Blog Comments</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Location</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="countries.html">Countries</a></li>
											<li><a href="states.html">States</a></li>
											<li><a href="cities.html">Cities</a></li>
										</ul>
									</li>
									<li><a href="testimonials.html"><span>Testimonials</span></a></li>
									<li><a href="faq.html"><span>FAQ</span></a></li>
									<li><a href="users.html"><span>Users</span></a></li>
									<li><a href="roles-permissions.html"><span>Roles & Permissions</span></a></li>
									<li><a href="delete-account.html"><span>Delete Account Request</span></a></li>
									<li><a href="profile.html"><span>Profile</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Authentication</span><span class="menu-arrow"></span></a>
										<ul>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Login<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="signin.html">Cover</a></li>
													<li><a href="signin-2.html">Illustration</a></li>
													<li><a href="signin-3.html">Basic</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Register<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="register.html">Cover</a></li>
													<li><a href="register-2.html">Illustration</a></li>
													<li><a href="register-3.html">Basic</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Forgot Password<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="forgot-password.html">Cover</a></li>
													<li><a href="forgot-password-2.html">Illustration</a></li>
													<li><a href="forgot-password-3.html">Basic</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Reset Password<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="reset-password.html">Cover</a></li>
													<li><a href="reset-password-2.html">Illustration</a></li>
													<li><a href="reset-password-3.html">Basic</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Email Verification<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="email-verification.html">Cover</a></li>
													<li><a href="email-verification-2.html">Illustration</a></li>
													<li><a href="email-verification-3.html">Basic</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">2 Step Verification<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="two-step-verification.html">Cover</a></li>
													<li><a href="two-step-verification-2.html">Illustration</a></li>
													<li><a href="two-step-verification-3.html">Basic</a></li>
												</ul>
											</li>
											<li><a href="lock-screen.html">Lock Screen</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Error Pages</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="error-404.html">404 Error </a></li>
											<li><a href="error-500.html">500 Error </a></li>
										</ul>
									</li>
									<li>
										<a href="blank-page.html"><span>Blank Page</span> </a>
									</li>
									<li>
										<a href="pricing.html"><span>Pricing</span> </a>
									</li>
									<li>
										<a href="coming-soon.html"><span>Coming Soon</span> </a>
									</li>
									<li>
										<a href="under-maintenance.html"><span>Under Maintenance</span> </a>
									</li>
								</ul>
							</div>
							<div class="tab-pane fade" id="settings">
								<ul>
									<li class="menu-title"><span>Settings</span></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>General Settings</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="general-settings.html">Profile</a></li>
											<li><a href="security-settings.html">Security</a></li>
											<li><a href="notification.html">Notifications</a></li>
											<li><a href="connected-apps.html">Connected Apps</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Website Settings</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="system-settings.html">System Settings</a></li>
											<li><a href="company-settings.html">Company Settings </a></li>
											<li><a href="localization-settings.html">Localization</a></li>
											<li><a href="prefixes.html">Prefixes</a></li>
											<li><a href="preference.html">Preference</a></li>
											<li><a href="appearance.html">Appearance</a></li>
											<li><a href="social-authentication.html">Social Authentication</a></li>
											<li><a href="language-settings.html">Language</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>App Settings</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Invoice<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="invoice-settings.html">Invoice Settings</a></li>
													<li><a href="invoice-templates.html">Invoice Template</a></li>
												</ul>
											</li>
											<li><a href="printer-settings.html">Printer</a></li>
											<li><a href="pos-settings.html">POS</a></li>
											<li><a href="custom-fields.html">Custom Fields</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>System Settings</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Email<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="email-settings.html">Email Settings</a></li>
													<li><a href="email-templates.html">Email Template</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">SMS<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="sms-settings.html">SMS Settings</a></li>
													<li><a href="sms-templates.html">SMS Template</a></li>
												</ul>
											</li>
											<li><a href="otp-settings.html">OTP</a></li>
											<li><a href="gdpr-settings.html">GDPR Cookies</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Financial Settings</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="payment-gateway-settings.html">Payment Gateway</a></li>
											<li><a href="bank-settings-grid.html">Bank Accounts</a></li>
											<li><a href="tax-rates.html">Tax Rates</a></li>
											<li><a href="currency-settings.html">Currencies</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Other Settings</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="storage-settings.html">Storage</a></li>
											<li><a href="ban-ip-address.html">Ban IP Address</a></li>
										</ul>
									</li>
									<li>
										<a href="signin.html"><span>Logout</span> </a>
									</li>
								</ul>
							</div>
							<div class="tab-pane fade" id="ui-elements">
								<ul>
									<li class="menu-title"><span>Ui Interface</span></li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Base UI</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="ui-alerts.html">Alerts</a></li>
											<li><a href="ui-accordion.html">Accordion</a></li>
											<li><a href="ui-avatar.html">Avatar</a></li>
											<li><a href="ui-badges.html">Badges</a></li>
											<li><a href="ui-buttons.html">Buttons</a></li>
											<li><a href="ui-buttons-group.html">Button Group</a></li>
											<li><a href="ui-breadcrumb.html">Breadcrumb</a></li>
											<li><a href="ui-cards.html">Card</a></li>
											<li><a href="ui-carousel.html">Carousel</a></li>
											<li><a href="ui-collapse.html">Collapse</a></li>
											<li><a href="ui-dropdowns.html">Dropdowns</a></li>
											<li><a href="https://dreamspos.dreamstechnologies.com/retail-pos/html/ui-ratio">Ratio</a></li>
											<li><a href="ui-grid.html">Grid</a></li>
											<li><a href="ui-images.html">Images</a></li>
											<li><a href="ui-links.html">Links</a></li>
											<li><a href="ui-list-group.html">List Group</a></li>
											<li><a href="ui-modals.html">Modals</a></li>
											<li><a href="ui-offcanvas.html">Offcanvas</a></li>
											<li><a href="ui-pagination.html">Pagination</a></li>
											<li><a href="ui-popovers.html">Popovers</a></li>
											<li><a href="ui-progress.html">Progress</a></li>
											<li><a href="ui-placeholders.html">Placeholders</a></li>
											<li><a href="ui-spinner.html">Spinner</a></li>
											<li><a href="ui-nav-tabs.html">Tabs</a></li>
											<li><a href="ui-toasts.html">Toasts</a></li>
											<li><a href="ui-tooltips.html">Tooltips</a></li>
											<li><a href="ui-typography.html">Typography</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Advanced UI</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="ui-clipboard.html">Clipboard</a></li>
											<li><a href="ui-drag-drop.html">Drag & Drop</a></li>
											<li><a href="ui-lightbox.html">Lightbox</a></li>
											<li><a href="ui-scrollbar.html">Scrollbar</a></li>
											<li><a href="ui-sweetalerts.html">Sweet Alerts</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Charts</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="chart-apex.html">Apex Charts</a></li>
											<li><a href="chart-c3.html">Chart C3</a></li>
											<li><a href="chart-js.html">Chart Js</a></li>
											<li><a href="chart-morris.html">Morris Charts</a></li>
											<li><a href="chart-flot.html">Flot Charts</a></li>
											<li><a href="chart-peity.html">Peity Charts</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Icons</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li><a href="icon-fontawesome.html">Fontawesome Icons</a></li>
											<li><a href="icon-feather.html">Feather Icons</a></li>
											<li><a href="icon-ionic.html">Ionic Icons</a></li>
											<li><a href="icon-material.html">Material Icons</a></li>
											<li><a href="icon-pe7.html">Pe7 Icons</a></li>
											<li><a href="icon-simpleline.html">Simpleline Icons</a></li>
											<li><a href="icon-themify.html">Themify Icons</a></li>
											<li><a href="icon-weather.html">Weather Icons</a></li>
											<li><a href="icon-typicon.html">Typicon Icons</a></li>
											<li><a href="icon-flag.html">Flag Icons</a></li>
											<li><a href="icon-tabler.html">Tabler Icons</a></li>
											<li><a href="icon-bootstrap.html">Bootstrap Icons</a></li>
											<li><a href="icon-remix.html">Remix Icons</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);">
											<span>Forms</span><span class="menu-arrow"></span>
										</a>
										<ul>
											<li class="submenu submenu-two">
												<a href="javascript:void(0);">Form Elements<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="form-basic-inputs.html">Basic Inputs</a></li>
													<li><a href="form-checkbox-radios.html">Checkbox & Radios</a></li>
													<li><a href="form-input-groups.html">Input Groups</a></li>
													<li><a href="form-grid-gutters.html">Grid & Gutters</a></li>
													<li><a href="form-select.html">Form Select</a></li>
													<li><a href="form-mask.html">Input Masks</a></li>
													<li><a href="form-fileupload.html">File Uploads</a></li>
												</ul>
											</li>
											<li class="submenu submenu-two">
												<a href="javascript:void(0);">Layouts<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="form-horizontal.html">Horizontal Form</a></li>
													<li><a href="form-vertical.html">Vertical Form</a></li>
													<li><a href="form-floating-labels.html">Floating Labels</a></li>
												</ul>
											</li>
											<li><a href="form-validation.html">Form Validation</a></li>
											<li><a href="form-select2.html">Select2</a></li>
											<li><a href="form-wizard.html">Form Wizard</a></li>
											<li><a href="form-pickers.html">Form Picker</a></li>
										</ul>
									</li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Tables</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="tables-basic.html">Basic Tables </a></li>
											<li><a href="data-tables.html">Data Table </a></li>
										</ul>
									</li>
								</ul>
							</div>
							<div class="tab-pane fade" id="extras">
								<ul>
									<li class="menu-title"><span>Help</span></li>
									<li><a href="https://dreamspos.dreamstechnologies.com/documentation/html.html" target="_blank"><span>Documentation</span></a></li>
									<li><a href="https://dreamspos.dreamstechnologies.com/documentation/changelog.html" target="_blank"><span>Changelog v2.3.5</span></a></li>
									<li class="submenu">
										<a href="javascript:void(0);"><span>Multi Level</span><span class="menu-arrow"></span></a>
										<ul>
											<li><a href="javascript:void(0);">Level 1.1</a></li>
											<li class="submenu submenu-two"><a href="javascript:void(0);">Level 1.2<span class="menu-arrow inside-submenu"></span></a>
												<ul>
													<li><a href="javascript:void(0);">Level 2.1</a></li>
													<li class="submenu submenu-two submenu-three"><a href="javascript:void(0);">Level 2.2<span class="menu-arrow inside-submenu inside-submenu-two"></span></a>
														<ul>
															<li><a href="javascript:void(0);">Level 3.1</a></li>
															<li><a href="javascript:void(0);">Level 3.2</a></li>
														</ul>
													</li>
												</ul>
											</li>
										</ul>
									</li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- /Two Col Sidebar -->
		
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
											<h5 class="mb-1">{{ __('pos.screen.welcome', ['name' => auth()->user()->name]) }}</h5>
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
											<div class="flex-grow-1">
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
												<td class="text-danger text-end" id="pos-discount">0.00</td>
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
					<input type="text" inputmode="decimal" class="form-control" id="pos-discount-value" placeholder="0">
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
			heldIndex: @json(route('pos.held.index')),
			heldStore: @json(route('pos.held.store')),
			heldBase: @json(url('/pos/held'))
		}
	};
</script>
<script src="{{ asset('js/pos-calc.js') }}"></script>
<script src="{{ asset('js/pos.js') }}"></script>
@endpush
