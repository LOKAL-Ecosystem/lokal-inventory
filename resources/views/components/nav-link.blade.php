@props(['href', 'icon', 'active' => false])

<li>
    <a href="{{ $href }}"
        class="{{ $active ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-base-content/70 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 min-h-11 transition-all">
        <i data-lucide="{{ $icon }}" class="w-4.5 h-4.5"></i>
        {{ $slot }}
    </a>
</li>
