@props(['label', 'name', 'hint' => null])

<div {{ $attributes->class(['space-y-1']) }}>
    <label for="{{ $attributes->get('for', $name) }}" class="block text-xs font-bold text-base-content/70">{{ $label }}</label>
    {{ $slot }}
    @if($hint)
        <p class="text-[11px] text-base-content/50">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-[11px] text-error font-semibold">{{ $message }}</p>
    @enderror
</div>
