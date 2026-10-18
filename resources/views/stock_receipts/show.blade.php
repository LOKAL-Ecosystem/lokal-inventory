@extends('layouts.app')

@section('title', 'Detail Stok Masuk')

@section('content')
<x-page-header :title="'Stok Masuk ' . $receipt->received_at->format('d M Y')" :subtitle="($receipt->supplier?->name ?? 'Tanpa supplier') . ' · dicatat oleh ' . ($receipt->creator?->name ?? '-')">
    <x-slot:actions>
        <a href="{{ route('stock-receipts.index') }}" class="btn btn-ghost btn-sm min-h-11 gap-2"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </x-slot:actions>
</x-page-header>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <table class="table">
        <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
            <tr><th>Bahan</th><th class="text-right">Jumlah beli</th><th class="text-right">Satuan dasar</th><th class="text-right">Harga</th><th class="text-right">Per satuan dasar</th></tr>
        </thead>
        <tbody class="[&>tr:hover]:bg-base-200/30">
            @foreach($receipt->items as $item)
                <tr>
                    <td class="font-extrabold">{{ $item->ingredient->name }}</td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($item->purchase_qty, 3, ',', '.'), '0'), ',') }} {{ $item->ingredient->purchase_unit ?? $item->ingredient->base_unit->value }}</td>
                    <td class="text-right">{{ $item->ingredient->formatQty($item->base_qty) }}</td>
                    <td class="text-right font-bold">Rp {{ number_format($item->total_price, 0, ',', '.') }}</td>
                    <td class="text-right text-xs">Rp {{ $item->base_qty > 0 ? rtrim(rtrim(number_format($item->total_price / $item->base_qty, 4, ',', '.'), '0'), ',') : 0 }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right font-extrabold">Total</td>
                <td class="text-right font-extrabold">Rp {{ number_format($receipt->total_cost, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

@if($receipt->note)
    <p class="text-xs text-base-content/70">Catatan: {{ $receipt->note }}</p>
@endif
@endsection
