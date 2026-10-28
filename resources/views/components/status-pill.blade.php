@props(['tone' => 'neutral', 'upper' => true])

@php
    // Warna pil status sesuai design-system.md §5.5 (satu makna, satu warna).
    $tones = [
        'success' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
        'warning' => 'bg-amber-50 text-amber-700 border-amber-200/60',
        'error' => 'bg-red-50 text-red-700 border-red-200/60',
        'neutral' => 'bg-base-200 text-base-content/60 border-base-300',
        'info' => 'bg-primary/10 text-primary border-primary/30',
    ];
@endphp

{{-- upper=false untuk angka bersatuan (mis. "120 ml"), supaya satuan tidak ikut kapital. --}}
<span {{ $attributes->class(['inline-block px-2 py-0.5 text-[10px] font-extrabold rounded-md border whitespace-nowrap', 'uppercase' => $upper, $tones[$tone] ?? $tones['neutral']]) }}>{{ $slot }}</span>
