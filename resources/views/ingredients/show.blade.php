@extends('layouts.app')

@section('title', $ingredient->name)

@section('content')
<x-page-header :title="$ingredient->name" :subtitle="'Riwayat pergerakan stok' . ($ingredient->category ? ' · ' . $ingredient->category->name : '')">
    <x-slot:actions>
        <a href="{{ route('ingredients.index') }}" class="btn btn-ghost btn-sm min-h-11 gap-2"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </x-slot:actions>
</x-page-header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Stok saat ini</div>
        <div class="text-2xl font-extrabold mt-1 {{ $ingredient->isNegative() ? 'text-error' : 'text-neutral' }}">{{ $ingredient->formatQty($ingredient->current_stock) }}</div>
    </div>
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Minimum</div>
        <div class="text-2xl font-extrabold mt-1">{{ $ingredient->formatQty($ingredient->min_stock) }}</div>
    </div>
    @can('view-costs')
        <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Biaya rata-rata</div>
            <div class="text-2xl font-extrabold mt-1">Rp {{ rtrim(rtrim(number_format($ingredient->avg_cost, 4, ',', '.'), '0'), ',') }}<span class="text-xs font-bold text-base-content/50"> / {{ $ingredient->base_unit->value }}</span></div>
        </div>
        <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Nilai stok</div>
            <div class="text-2xl font-extrabold mt-1">Rp {{ number_format(max(0, (float) $ingredient->current_stock) * (float) $ingredient->avg_cost, 0, ',', '.') }}</div>
        </div>
    @endcan
</section>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                <tr>
                    <th>Waktu</th>
                    <th>Jenis</th>
                    <th class="text-right">Jumlah</th>
                    <th>Keterangan</th>
                    <th>Oleh</th>
                </tr>
            </thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($movements as $movement)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $movement->created_at->format('d M Y H:i') }}</td>
                        <td><span class="badge {{ (float) $movement->qty >= 0 ? 'badge-success' : 'badge-warning' }} font-bold">{{ $movement->type->label() }}</span></td>
                        <td class="text-right font-black {{ (float) $movement->qty >= 0 ? 'text-success' : 'text-error' }}">
                            {{ (float) $movement->qty > 0 ? '+' : '' }}{{ $ingredient->formatQty($movement->qty) }}
                        </td>
                        <td class="text-xs text-base-content/70">
                            {{ $movement->reasonLabel() }}
                            @if($movement->pos_order_uuid) Pesanan POS <span class="font-mono text-xs font-bold bg-base-200 border border-base-300 px-2 py-0.5 rounded-md">{{ \Illuminate\Support\Str::limit($movement->pos_order_uuid, 8, '') }}</span> @endif
                            {{ $movement->note }}
                        </td>
                        <td class="text-xs">{{ $movement->creator?->name ?? ($movement->pos_order_uuid ? 'POS' : '-') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-12 text-xs text-base-content/50">Belum ada pergerakan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movements->hasPages())
        <div class="p-4 border-t border-base-200">{{ $movements->links() }}</div>
    @endif
</div>
@endsection
