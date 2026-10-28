@extends('layouts.app')

@section('title', 'Resep')

@section('content')
<x-page-header title="Resep" subtitle="Menu berasal dari katalog Lokal POS. Atur bahan & takaran per menu untuk memotong stok otomatis.">
    <x-slot:actions>
        <form method="POST" action="{{ route('settings.sync') }}">
            @csrf
            <button class="btn btn-outline rounded-lg btn-sm min-h-11 gap-2"><i data-lucide="refresh-cw" class="w-4 h-4"></i> Sinkron katalog</button>
        </form>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="bg-base-100 border border-base-300 rounded-2xl p-4 flex flex-col sm:flex-row gap-3">
    <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari menu…" class="input flex-1 min-h-11 text-sm rounded-xl" />
    <label class="flex items-center gap-2 text-xs font-bold min-h-11">
        <input type="checkbox" name="filter" value="no_recipe" class="checkbox checkbox-sm checkbox-primary" @checked(request('filter') === 'no_recipe') onchange="this.form.submit()" />
        Belum ada resep
    </label>
    <button class="btn btn-neutral rounded-xl min-h-11 font-bold">Cari</button>
</form>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                <tr>
                    <th>Menu</th>
                    <th class="text-right">Harga jual</th>
                    <th class="text-right">HPP</th>
                    <th class="text-right">Margin</th>
                    <th class="text-right">Porsi tersedia</th>
                    <th class="text-center">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($rows as $row)
                    @php($p = $row['product'])
                    @php($s = $row['summary'])
                    <tr>
                        <td>
                            <div class="font-extrabold text-neutral">{{ $p->name }}</div>
                            <div class="text-[11px] text-base-content/50">{{ $p->category ?? '-' }}</div>
                        </td>
                        <td class="text-right">Rp {{ number_format($p->price, 0, ',', '.') }}</td>
                        @if($s['has_recipe'])
                            <td class="text-right">Rp {{ number_format($s['hpp'], 0, ',', '.') }}</td>
                            <td class="text-right font-bold {{ $s['margin'] < 0 ? 'text-error' : 'text-success' }}">
                                Rp {{ number_format($s['margin'], 0, ',', '.') }}
                                @if($s['margin_pct'] !== null)<span class="text-[11px] text-base-content/50">({{ $s['margin_pct'] }}%)</span>@endif
                            </td>
                            <td class="text-right font-bold">{{ $row['portions'] }}</td>
                        @else
                            <td class="text-right text-base-content/40">-</td><td class="text-right text-base-content/40">-</td><td class="text-right text-base-content/40">-</td>
                        @endif
                        <td class="text-center">
                            @if(! $s['has_recipe']) <x-status-pill tone="warning">Tanpa resep</x-status-pill>
                            @elseif($p->auto_unavailable) <x-status-pill tone="error">Habis (stok)</x-status-pill>
                            @elseif($p->availability_source === 'manual') <x-status-pill title="Ditandai habis oleh pemilik di POS. Tidak diubah otomatis oleh stok.">Habis (manual di POS)</x-status-pill>
                            @elseif(! $p->is_available) <x-status-pill>Habis di POS</x-status-pill>
                            @else <x-status-pill tone="success">Tersedia</x-status-pill> @endif
                        </td>
                        <td class="text-right"><a href="{{ route('recipes.show', $p->pos_product_id) }}" class="btn btn-ghost btn-sm min-h-11">Atur</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-12 text-xs text-base-content/50">Belum ada menu. Atur koneksi POS lalu sinkron katalog.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
        <div class="p-4 border-t border-base-200">{{ $products->links() }}</div>
    @endif
</div>
@endsection
