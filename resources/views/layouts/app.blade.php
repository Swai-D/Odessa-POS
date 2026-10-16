<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-layout-mode="light_mode">

<head>

	<!-- Meta Tags -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Odessa POS: sales, stock, purchases and reports for your shop.">
	<meta name="keywords" content="POS, inventory, sales, shop management">
	<meta name="author" content="Odessa Lab Technologies">
	<meta name="robots" content="noindex, nofollow">
	<title>@yield('title', 'Odessa POS')</title>

	<script src="{{ asset('assets/js/theme-script.js') }}"></script>	

	<!-- Favicon -->
	<link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">

	<!-- Apple Touch Icon -->
	<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/apple-touch-icon.png') }}">

	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">

	<!-- Datetimepicker CSS -->

	<!-- animation CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/animate.css') }}">

	<!-- Select2 CSS -->

	<!-- Daterangepikcer CSS -->

	<!-- Tabler Icon CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/tabler-icons/tabler-icons.min.css') }}">

	<!-- Fontawesome CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/fontawesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}">

	  

	<!-- Main CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">

	<link rel="stylesheet" href="{{ asset('assets/plugins/flatpickr/flatpickr.min.css') }}">
    @stack('extra-css')
</head>

<body>
	<div id="global-loader">
		<div class="whirly-loader"> </div>
	</div>
	<!-- Main Wrapper -->
	<div class="main-wrapper">
    @include('partials.header')
    @include('partials.sidebar')
    <div class="page-wrapper">
        @include('partials.subscription-banner')
        @yield('content')
        @include('partials.footer')
    </div>
</div>
@stack('modals')
@include('partials.theme-settings')

<!-- Feather Icon JS -->
	<script src="{{ asset('assets/js/feather.min.js') }}"></script>

	<!-- Slimscroll JS -->
	<script src="{{ asset('assets/plugins/simplebar/simplebar.min.js') }}"></script>

	<!-- Bootstrap Core JS -->
	<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

	<!-- ApexChart JS -->
	<script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}"></script>
	<script src="{{ asset('assets/plugins/apexchart/chart-data.js') }}"></script>

	<!-- Chart JS -->
	<script src="{{ asset('assets/plugins/chartjs/chart.min.js') }}"></script>
	<script src="{{ asset('assets/plugins/chartjs/chart-data.js') }}"></script>

	<!-- Daterangepikcer JS -->

	<!-- Page vendor JS (must load before script.js, e.g. datatable.js) -->
	@stack('vendor-js')

	<!-- Select2 JS -->
	<script src="{{ asset('assets/plugins/tom-select/js/tom-select.complete.min.js') }}"></script>

	  

	 <!-- Custom JS -->
	<script src="{{ asset('assets/plugins/flatpickr/flatpickr.min.js') }}"></script>
	<script src="{{ asset('assets/js/daterangepicker.js') }}"></script>
	<script src="{{ asset('assets/js/script.js') }}"></script>

	

    <script src="{{ asset('js/pwa-register.js') }}"></script>
    @stack('extra-js')
</body>
</html>
