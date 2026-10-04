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
		<title>@yield('title', 'Sign In')</title>

		<!-- Favicon -->
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">

		<!-- Apple Touch Icon -->
		<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/apple-touch-icon.png') }}">
		
		<!-- Bootstrap CSS -->
        <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
		
        <!-- Fontawesome CSS -->
		<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/fontawesome.min.css') }}">
		<link rel="stylesheet" href="{{ asset('assets/plugins/fontawesome/css/all.min.css') }}">

        <!-- Tabler Icon CSS -->
	    <link rel="stylesheet" href="{{ asset('assets/plugins/tabler-icons/tabler-icons.min.css') }}">

	    <!-- Main CSS -->
        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
		
            @stack('extra-css')
    </head>
    <body class="account-page"><a href="https://dreamspos.dreamstechnologies.com/cdn-cgi/content?id=CvjkLcNEbaRBmezljA_4p4L0fSFJQAk64afyZfexST0-1791050856.1784859-1.2.1.1-m3n67NAwcvZf48wT8omTXLALxiGt_Yj4vBpc66EbDncL1LLIp6_2GM.yPbjbAKPI" aria-hidden="true" rel="nofollow noopener" style="display: none !important; visibility: hidden !important"></a>

        <div id="global-loader" >
			<div class="whirly-loader"> </div>
		</div>

		<!-- Main Wrapper -->
        
    @yield('content')
<!-- Feather Icon JS -->
		<script src="{{ asset('assets/js/feather.min.js') }}"></script>
		
		<!-- Bootstrap Core JS -->
        <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
		
		<!-- Custom JS -->
        <script src="{{ asset('assets/js/script.js') }}"></script>

    
    @stack('extra-js')
</body>
</html>
