@extends('layouts.app')

@section('title', 'Stok Opname')

@section('content')
<x-page-header title="Stok Opname" subtitle="Bandingkan stok fisik dengan stok sistem. Selisih dicatat sebagai penyesuaian saat opname diselesaikan.">
    <x-slot:actions>
        <button type="button" onclick="document.getElementById('modal-opname').showModal()" class="btn btn-primary btn-sm min-h-11 gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Opname Baru
        </button>
    </x-slot:actions>
</x-page-header>

<div class="card-premium overflow-hidden">
    <table class="table-premium">
        <thead>
            <tr><th>Tanggal</th><th>Status</th><th class="text-right">Bahan</th><th>Dibuat oleh</th><th>Catatan</th><th></th></tr>
        </thead>
        <tbody>
            @forelse($opnames as $opname)
                <tr>
                    <td class="font-bold">{{ $opname->opname_date->format('d M Y') }}</td>
                    <td><span class="badge {{ $opname->isCompleted() ? 'badge-success' : 'badge-warning' }} font-bold">{{ $opname->status->label() }}</span></td>
                    <td class="text-right">{{ $opname->items_count }}</td>
                    <td class="text-xs">{{ $opname->creator?->name ?? '-' }}</td>
                    <td class="text-xs">{{ $opname->note ?? '-' }}</td>
                    <td class="text-right"><a href="{{ route('opnames.show', $opname) }}" class="btn btn-ghost btn-sm min-h-11">{{ $opname->isCompleted() ? 'Lihat' : 'Lanjutkan' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-12 text-xs text-base-content/50">Belum ada opname.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($opnames->hasPages())
        <div class="p-4 border-t border-base-200">{{ $opnames->links() }}</div>
    @endif
</div>

<x-modal id="modal-opname" title="Opname Baru">
    <form method="POST" action="{{ route('opnames.store') }}" class="space-y-3">
        @csrf
        <p class="text-xs text-base-content/60">Stok sistem semua bahan aktif akan disimpan sebagai snapshot saat opname dibuat.</p>
        <x-field label="Tanggal" name="opname_date">
            <input type="date" name="opname_date" value="{{ now()->toDateString() }}" required class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <x-field label="Catatan (opsional)" name="note">
            <input type="text" name="note" maxlength="500" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
            <button type="submit" class="btn btn-primary min-h-11">Mulai</button>
        </div>
    </form>
</x-modal>
@endsection
