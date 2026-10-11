<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>

		<!-- Meta Tags -->
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Odessa POS: sales, stock, purchases and reports for your shop.">
		<meta name="keywords" content="POS, inventory, sales, shop management">
		<meta name="author" content="Odessa Lab Technologies">
		<meta name="robots" content="noindex, nofollow">
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
    <body class="account-page">

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
