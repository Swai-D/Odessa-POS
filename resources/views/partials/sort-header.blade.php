{{-- Clickable column header. Needs $label, $key, and $sort / $dir from the controller (Sort::apply). --}}
@php
	$active = ($sort ?? null) === $key;
	$next = $active && ($dir ?? 'asc') === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]) }}" class="text-reset text-nowrap" @if ($active) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
	{{ $label }}@if ($active)<i class="ti ti-arrow-{{ $dir === 'asc' ? 'up' : 'down' }} ms-1"></i>@endif
</a>
