@props(['route', 'icon', 'label', 'match' => null])

@php
    $active = request()->routeIs($match ?? $route);
@endphp

<li>
    <a href="{{ route($route) }}"
       data-tip="{{ $label }}"
       @if($active) aria-current="page" @endif
       @class([
           'is-drawer-close:tooltip is-drawer-close:tooltip-right is-drawer-close:justify-center rounded-xl gap-3 py-2.5',
           'active bg-primary text-primary-content hover:bg-primary' => $active,
           'hover:bg-base-200' => ! $active,
       ])>
        <i data-lucide="{{ $icon }}" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
        <span class="is-drawer-close:hidden">{{ $label }}</span>
    </a>
</li>
