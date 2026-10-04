<aside class="sidebar">
    <nav aria-label="{{ __('app.name') }}">
        @foreach (config('menu') as $item)
            @if (! $item['permission'] || auth()->user()?->can($item['permission']))
                <a href="{{ route($item['route']) }}" @class(['active' => request()->routeIs($item['route'])])>
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>