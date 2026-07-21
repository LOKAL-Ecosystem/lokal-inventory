@extends('layouts.app')

@section('title', 'Kartu Stok & Mutasi')

@section('content')
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Kartu Stok & Riwayat Mutasi</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Lacak setiap pergerakan stok masuk, stok keluar transaksi POS, dan adjustment saldo berjalan.</p>
    </div>
</header>

<!-- Filter Card -->
<div class="card-premium p-4 shadow-xs">
    <form method="GET" action="{{ route('movements.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
        <div class="space-y-1">
            <label class="text-xs font-bold text-neutral">Filter Item Stok</label>
            <select name="item_id" class="select select-bordered w-full px-3.5 py-2.5 text-xs rounded-xl font-medium" onchange="this.form.submit()">
                <option value="">-- Semua Item Stok --</option>
                @foreach($items as $it)
                    <option value="{{ $it->id }}" {{ request('item_id') == $it->id ? 'selected' : '' }}>{{ $it->name }} ({{ $it->sku }})</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-bold text-neutral">Tipe Mutasi</label>
            <select name="type" class="select select-bordered w-full px-3.5 py-2.5 text-xs rounded-xl font-medium" onchange="this.form.submit()">
                <option value="">-- Semua Tipe --</option>
                <option value="stock_in" {{ request('type') == 'stock_in' ? 'selected' : '' }}>Stock In (Restock)</option>
                <option value="stock_out_pos" {{ request('type') == 'stock_out_pos' ? 'selected' : '' }}>Stock Out (POS Order)</option>
                <option value="adjustment_add" {{ request('type') == 'adjustment_add' ? 'selected' : '' }}>Adjustment (+)</option>
                <option value="adjustment_sub" {{ request('type') == 'adjustment_sub' ? 'selected' : '' }}>Adjustment (-)</option>
            </select>
        </div>
        <div class="space-y-1">
            <label class="text-xs font-bold text-neutral">Dari Tanggal</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="input input-bordered w-full px-3.5 py-2.5 text-xs rounded-xl font-medium" />
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn btn-neutral text-white btn-sm px-6 py-2.5 h-auto flex-1 rounded-xl font-bold">Filter Mutasi</button>
            @if(request()->hasAny(['item_id', 'type', 'date_from']))
                <a href="{{ route('movements.index') }}" class="btn btn-ghost btn-sm text-gray-500 rounded-xl font-semibold">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Item Details Header if Filtered -->
@if($selectedItem)
<div class="card-premium bg-primary/5 border-primary/20 p-5 flex flex-row items-center justify-between shadow-xs">
    <div class="space-y-1">
        <div class="text-[10px] font-extrabold uppercase tracking-widest text-primary">Kartu Stok Terpilih</div>
        <div class="text-lg font-black text-neutral">{{ $selectedItem->name }} <span class="text-xs font-mono text-gray-500">({{ $selectedItem->sku }})</span></div>
    </div>
    <div class="text-right">
        <div class="text-xs text-gray-500 font-medium">Saldo Stok Saat Ini</div>
        <div class="text-2xl font-black text-primary">{{ number_format($selectedItem->quantity_on_hand, 2) }} <span class="text-sm font-semibold text-gray-400">{{ $selectedItem->unit?->symbol }}</span></div>
    </div>
</div>
@endif

<!-- Table Movements -->
<div class="card-premium overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>Waktu / Tanggal</th>
                    <th>Nama Item</th>
                    <th>Tipe Mutasi</th>
                    <th>No. Referensi</th>
                    <th class="text-right">Saldo Awal</th>
                    <th class="text-right">Perubahan Qty</th>
                    <th class="text-right">Saldo Akhir</th>
                    <th>Keterangan</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $mov)
                <tr>
                    <td class="text-xs font-mono text-gray-400 whitespace-nowrap">{{ $mov->created_at->format('d/m/Y H:i:s') }}</td>
                    <td class="font-bold text-neutral text-xs">
                        {{ $mov->item?->name ?? 'Deleted Item' }}
                        <div class="text-[10px] text-gray-400 font-mono">{{ $mov->item?->sku }}</div>
                    </td>
                    <td>
                        @if($mov->type == 'stock_in')
                            <span class="badge badge-success text-white badge-sm font-bold shadow-xs">Stock In</span>
                        @elseif($mov->type == 'stock_out_pos')
                            <span class="badge badge-primary text-white badge-sm font-bold shadow-xs">POS Order</span>
                        @elseif($mov->type == 'initial')
                            <span class="badge badge-neutral badge-sm font-bold shadow-xs">Saldo Awal</span>
                        @else
                            <span class="badge badge-warning text-neutral badge-sm font-bold shadow-xs">Adjustment</span>
                        @endif
                    </td>
                    <td class="font-mono text-xs font-bold text-neutral">{{ $mov->reference_no ?? '-' }}</td>
                    <td class="text-right text-xs text-gray-500 font-semibold">
                        {{ number_format($mov->quantity_before, 2) }}
                    </td>
                    <td class="text-right font-black text-xs {{ $mov->quantity_change >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $mov->quantity_change > 0 ? '+' : '' }}{{ number_format($mov->quantity_change, 2) }}
                    </td>
                    <td class="text-right font-bold text-xs text-neutral">
                        {{ number_format($mov->quantity_after, 2) }} <span class="text-[10px] text-gray-400">{{ $mov->item?->unit?->symbol }}</span>
                    </td>
                    <td class="text-xs text-gray-500 max-w-xs truncate">{{ $mov->description ?? '-' }}</td>
                    <td class="text-xs text-gray-500 font-medium">{{ $mov->user?->name ?? 'System/POS' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-10 text-gray-400 text-xs font-medium">
                        <i data-lucide="history" class="w-8 h-8 mx-auto mb-2 text-gray-300"></i>
                        Belum ada riwayat mutasi stok.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($movements->hasPages())
    <div class="p-4 border-t border-base-200">
        {{ $movements->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
