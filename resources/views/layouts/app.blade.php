<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    @stack('extra-css')
</head>
<body>
    <div class="main-wrapper">
        @include('partials.header')
        @include('partials.sidebar')
        <main class="page-wrapper">
            <div class="content">
                <h1 class="page-title">@yield('page-title', __('app.name'))</h1>
                @yield('content')
            </div>
        </main>
        @include('partials.footer')
        @include('partials.theme-settings')
    </div>
    <script src="{{ asset('assets/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/script.js') }}"></script>
    @stack('extra-js')
</body>
</html>