@extends('layouts.app')

@section('title', 'Stok Masuk')

@section('content')
<x-page-header title="Stok Masuk" subtitle="Riwayat belanja bahan baku. Setiap belanja memperbarui stok dan biaya rata-rata.">
    <x-slot:actions>
        <a href="{{ route('stock-receipts.create') }}" class="btn btn-primary btn-sm min-h-11 gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Catat Belanja</a>
    </x-slot:actions>
</x-page-header>

<div class="card-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr><th>Tanggal</th><th>Supplier</th><th class="text-right">Item</th><th class="text-right">Total</th><th>Dicatat oleh</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($receipts as $receipt)
                    <tr>
                        <td class="font-bold">{{ $receipt->received_at->format('d M Y') }}</td>
                        <td>{{ $receipt->supplier?->name ?? '-' }}</td>
                        <td class="text-right">{{ $receipt->items_count }}</td>
                        <td class="text-right font-extrabold">Rp {{ number_format($receipt->total_cost, 0, ',', '.') }}</td>
                        <td class="text-xs">{{ $receipt->creator?->name ?? '-' }}</td>
                        <td class="text-right"><a href="{{ route('stock-receipts.show', $receipt) }}" class="btn btn-ghost btn-sm min-h-11">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-12 text-xs text-base-content/50">Belum ada stok masuk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($receipts->hasPages())
        <div class="p-4 border-t border-base-200">{{ $receipts->links() }}</div>
    @endif
</div>
@endsection
