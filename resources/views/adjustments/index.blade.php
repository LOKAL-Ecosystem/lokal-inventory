@extends('layouts.app')

@section('title', 'Stock Adjustment / Opname')

@section('content')
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Stock Adjustment & Opname</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Koreksi stok manual (rusak, hilang, selisih hitung) dan status persetujuan approval Admin.</p>
    </div>
    <a href="{{ route('adjustments.create') }}" class="btn btn-warning text-neutral btn-sm flex items-center gap-2 px-5 py-2.5 h-auto shadow-md">
        <i data-lucide="sliders" class="w-4 h-4"></i>
        Buat Koreksi Stok Baru
    </a>
</header>

<div class="card-premium overflow-hidden shadow-xs space-y-0">
    <div class="p-4 border-b border-base-200 bg-base-100">
        <form method="GET" action="{{ route('adjustments.index') }}" class="flex gap-3 max-w-md">
            <div class="flex-1 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari No. Referensi Adjustment..." class="input w-full pl-10 pr-4 py-2.5 text-xs rounded-xl font-medium" />
                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3"></i>
            </div>
            <button type="submit" class="btn btn-neutral text-white btn-sm px-6 py-2.5 h-auto rounded-xl font-bold">Cari</button>
            @if(request('search'))
                <a href="{{ route('adjustments.index') }}" class="btn btn-ghost btn-sm text-gray-500 font-semibold">Reset</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>No. Referensi</th>
                    <th>Waktu / Tanggal</th>
                    <th>Alasan Koreksi</th>
                    <th>Dibuat Oleh</th>
                    <th>Status Approval</th>
                    <th>Approved By</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                <tr>
                    <td><span class="code-chip">{{ $adj->reference_no }}</span></td>
                    <td class="text-xs text-neutral font-medium whitespace-nowrap">{{ $adj->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @php
                            $reasons = [
                                'damaged' => 'Barang Rusak',
                                'lost' => 'Barang Hilang',
                                'stock_opname_discrepancy' => 'Selisih Stock Opname',
                                'other' => 'Koreksi Lainnya',
                            ];
                        @endphp
                        <span class="badge badge-neutral text-xs font-semibold">{{ $reasons[$adj->reason] ?? $adj->reason }}</span>
                        @if($adj->notes)
                            <div class="text-[11px] text-gray-400 italic mt-0.5">{{ $adj->notes }}</div>
                        @endif
                    </td>
                    <td class="text-xs text-gray-700 font-bold">{{ $adj->user?->name ?? 'Staff' }}</td>
                    <td>
                        @if($adj->status == 'approved')
                            <span class="badge badge-success">Approved</span>
                        @elseif($adj->status == 'pending')
                            <span class="badge badge-warning">Pending Approval</span>
                        @else
                            <span class="badge badge-error">Rejected</span>
                        @endif
                    </td>
                    <td class="text-xs text-gray-500 font-medium">{{ $adj->approver?->name ?? '-' }}</td>
                    <td class="text-center">
                        @if($adj->status == 'pending' && auth()->user()->isAdmin())
                            <form action="{{ route('adjustments.approve', $adj->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-success text-white font-bold rounded-lg px-3 py-1 shadow-xs">Approve</button>
                            </form>
                        @else
                            <span class="text-xs text-gray-400 font-medium">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400 text-xs font-medium">
                        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-3 border border-amber-200/60 shadow-xs">
                            <i data-lucide="sliders" class="w-5 h-5"></i>
                        </div>
                        <div class="font-bold text-neutral text-sm">Belum ada riwayat stock adjustment.</div>
                        <p class="text-xs text-gray-400 mt-1">Klik tombol "Buat Koreksi Stok Baru" untuk mencatat hasil opname fisik.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($adjustments->hasPages())
    <div class="p-4 border-t border-base-200">
        {{ $adjustments->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
