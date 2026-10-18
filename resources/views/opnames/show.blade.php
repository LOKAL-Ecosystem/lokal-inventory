@extends('layouts.app')

@section('title', 'Stok Opname')

@section('content')
@php $completed = $opname->isCompleted(); @endphp

<x-page-header :title="'Opname ' . $opname->opname_date->format('d M Y')" :subtitle="$completed ? 'Selesai ' . $opname->completed_at->format('d M Y H:i') . ' — tidak bisa diubah.' : 'Isi hitungan fisik tiap bahan, simpan, lalu selesaikan.'">
    <x-slot:actions>
        <a href="{{ route('opnames.index') }}" class="btn btn-ghost btn-sm min-h-11 gap-2"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('opnames.update', $opname) }}" id="count-form">
    @csrf @method('PUT')

    <div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                    <tr>
                        <th>Bahan</th>
                        <th class="text-right">Stok sistem</th>
                        <th class="text-right min-w-40">Hitungan fisik</th>
                        <th class="text-right">Selisih</th>
                    </tr>
                </thead>
                <tbody class="[&>tr:hover]:bg-base-200/30">
                    @foreach($opname->items->sortBy(fn ($i) => $i->ingredient->name) as $item)
                        @php $diff = $item->difference !== null ? (float) $item->difference : null; @endphp
                        <tr>
                            <td class="font-extrabold">{{ $item->ingredient->name }}</td>
                            <td class="text-right">{{ $item->ingredient->formatQty($item->system_qty) }}</td>
                            <td class="text-right">
                                @if($completed)
                                    {{ $item->counted_qty !== null ? $item->ingredient->formatQty($item->counted_qty) : '-' }}
                                @else
                                    <div class="flex items-center justify-end gap-2">
                                        <input type="number" min="0" step="any" name="counts[{{ $item->id }}]"
                                            value="{{ $item->counted_qty !== null ? (float) $item->counted_qty : '' }}"
                                            data-system="{{ (float) $item->system_qty }}"
                                            class="input w-32 min-h-11 text-sm rounded-xl text-right count-input" />
                                        <span class="text-xs font-bold text-base-content/60">{{ $item->ingredient->base_unit->value }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-right font-black diff {{ $diff === null ? '' : ($diff < 0 ? 'text-error' : ($diff > 0 ? 'text-success' : '')) }}">
                                @if($diff !== null) {{ $diff > 0 ? '+' : '' }}{{ $item->ingredient->formatQty($diff) }} @else - @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</form>

@unless($completed)
    <div class="flex flex-wrap justify-end gap-3">
        <button type="submit" form="count-form" class="btn btn-outline min-h-11">Simpan Hitungan</button>
        @can('complete-opname')
            <form method="POST" action="{{ route('opnames.complete', $opname) }}" id="complete-form">
                @csrf
                <button type="submit" class="btn btn-primary min-h-11">Selesaikan Opname</button>
            </form>
        @endcan
    </div>
@endunless
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.count-input').forEach((input) => {
        input.addEventListener('input', () => {
            const cell = input.closest('tr').querySelector('.diff');
            if (input.value === '') { cell.textContent = '-'; cell.className = 'text-right font-black diff'; return; }
            const diff = Math.round((parseFloat(input.value) - parseFloat(input.dataset.system)) * 1000) / 1000;
            cell.textContent = (diff > 0 ? '+' : '') + diff.toLocaleString('id-ID', { maximumFractionDigits: 3 });
            cell.className = 'text-right font-black diff ' + (diff < 0 ? 'text-error' : diff > 0 ? 'text-success' : '');
        });
    });

    document.getElementById('complete-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        Swal.fire({
            title: 'Selesaikan opname?',
            text: 'Hitungan yang belum disimpan tidak ikut. Setelah selesai, opname tidak bisa diubah dan selisih menjadi penyesuaian stok.',
            icon: 'warning', showCancelButton: true, confirmButtonText: 'Selesaikan', cancelButtonText: 'Batal',
        }).then((result) => result.isConfirmed && event.target.submit());
    });
</script>
@endpush
