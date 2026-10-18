@props(['title', 'subtitle' => null])

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-base-content">{{ $title }}</h1>
        @if($subtitle)
            <p class="text-sm text-base-content/60">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
