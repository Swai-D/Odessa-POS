@extends('layouts.pos')

@section('title', __('app.menu.pos'))

@section('content')
<main class="pos-workspace">
    <header><a href="{{ route('dashboard') }}">{{ __('app.name') }}</a><h1>{{ __('app.menu.pos') }}</h1></header>
    <p>{{ __('app.setup_placeholder') }}</p>
</main>
@endsection