<header class="header">
    <a class="brand" href="{{ route('dashboard') }}">{{ __('app.name') }}</a>
    <nav aria-label="{{ __('app.language') }}">
        @foreach (['en' => 'English', 'sw' => 'Kiswahili'] as $locale => $label)
            <form method="post" action="{{ route('locale.switch', $locale) }}">
                @csrf
                <button type="submit">{{ $label }}</button>
            </form>
        @endforeach
    </nav>
    @auth
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">{{ __('app.sign_out') }}</button>
        </form>
    @endauth
</header>