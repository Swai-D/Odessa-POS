<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>

	<!-- Meta Tags -->
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="Dreams POS is a powerful Bootstrap based Inventory Management Admin Template designed for businesses, offering seamless invoicing, project tracking, and estimates.">
	<meta name="keywords" content="inventory management, admin dashboard, bootstrap template, invoicing, estimates, business management, responsive admin, POS system">
	<meta name="author" content="Dreams Technologies">
	<meta name="robots" content="index, follow">
	<title>@yield('title', 'POS')</title>

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

	<!-- Datatable CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/dataTables.bootstrap5.min.css') }}">
	
	<!-- Fontawesome CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/fontawesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}">

	<!-- Daterangepikcer CSS -->

	<!-- Tabler Icon CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/tabler-icons/tabler-icons.min.css') }}">

	<!-- Swiper CSS -->
	<link rel="stylesheet" href="{{ asset('assets/plugins/swiper/swiper-bundle.min.css') }}">

	<!-- Main CSS -->
	<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
	
	<link rel="stylesheet" href="{{ asset('assets/plugins/flatpickr/flatpickr.min.css') }}">
    @stack('extra-css')
</head>

<body class="pos-page"><a href="https://dreamspos.dreamstechnologies.com/cdn-cgi/content?id=71Yv3KCxK3hGZ9Hl4RB3u1ejRaBFtFIbrx2rbqwa6ks-1791050852.7817674-1.2.1.1-VH_sH_8YOWIbVhhhAZJzM2G5jFIvmNLFv6UcxO4q_Jdufjtu_5jxS_MTRmOi2nnX" aria-hidden="true" rel="nofollow noopener" style="display: none !important; visibility: hidden !important"></a>

	<div id="global-loader" >
		<div class="whirly-loader"> </div>
	</div>
	
	<!-- Main Wrapper -->
	
    @yield('content')
<!-- Bootstrap Core JS -->
	<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>

	<!-- Feather Icon JS -->
	<script src="{{ asset('assets/js/feather.min.js') }}"></script>

	<!-- Slimscroll JS -->
	<script src="{{ asset('assets/plugins/simplebar/simplebar.min.js') }}"></script>

	<!-- Chart JS -->
	<script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}"></script>
	<script src="{{ asset('assets/plugins/apexchart/chart-data.js') }}"></script>

	<!-- Datatable JS -->
	<script src="{{ asset('assets/js/datatable.js') }}"></script>

	<!-- Daterangepikcer JS -->

	<!-- Swiper JS -->
	<script src="{{ asset('assets/plugins/swiper/swiper-bundle.min.js') }}"></script>

	<!-- Select2 JS -->
	<script src="{{ asset('assets/plugins/tom-select/js/tom-select.complete.min.js') }}"></script>

	<!-- Sticky-sidebar -->
	
	<!-- Custom JS -->
	<script src="{{ asset('assets/js/calculator.js') }}"></script>
	<script src="{{ asset('assets/plugins/flatpickr/flatpickr.min.js') }}"></script>
	<script src="{{ asset('assets/js/daterangepicker.js') }}"></script>
	<script src="{{ asset('assets/js/script.js') }}"></script>


    @stack('extra-js')
</body>
</html>
