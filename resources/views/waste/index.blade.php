@extends('layouts.app')

@section('title', 'Waste')

@section('content')
<x-page-header title="Bahan Terbuang (Waste)" subtitle="Catat bahan yang tumpah, rusak, kedaluwarsa, atau salah buat.">
    <x-slot:actions>
        <button type="button" onclick="document.getElementById('modal-waste').showModal()" class="btn btn-primary btn-sm min-h-11 gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Catat Waste
        </button>
    </x-slot:actions>
</x-page-header>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                <tr><th>Waktu</th><th>Bahan</th><th class="text-right">Jumlah</th><th>Alasan</th><th>Catatan</th><th>Oleh</th></tr>
            </thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($movements as $movement)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $movement->created_at->format('d M Y H:i') }}</td>
                        <td class="font-extrabold">{{ $movement->ingredient->name }}</td>
                        <td class="text-right font-black text-error">{{ $movement->ingredient->formatQty($movement->qty) }}</td>
                        <td><span class="badge badge-warning font-bold">{{ $movement->reasonLabel() }}</span></td>
                        <td class="text-xs">{{ $movement->note ?? '-' }}</td>
                        <td class="text-xs">{{ $movement->creator?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-12 text-xs text-base-content/50">Belum ada waste tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($movements->hasPages())
        <div class="p-4 border-t border-base-200">{{ $movements->links() }}</div>
    @endif
</div>

<x-modal id="modal-waste" title="Catat Bahan Terbuang">
    <form method="POST" action="{{ route('waste.store') }}" class="space-y-3">
        @csrf
        <x-idempotency-token />
        <x-field label="Bahan" name="ingredient_id">
            <select name="ingredient_id" id="waste-ingredient" required class="select w-full min-h-11 text-sm rounded-xl">
                <option value="">Pilih bahan…</option>
                @foreach($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}" data-base="{{ $ingredient->base_unit->value }}" data-purchase="{{ $ingredient->purchase_unit }}">{{ $ingredient->name }}</option>
                @endforeach
            </select>
        </x-field>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Jumlah" name="qty">
                <input type="number" name="qty" min="0.001" step="any" required class="input w-full min-h-11 text-sm rounded-xl" />
            </x-field>
            <x-field label="Satuan" name="unit">
                <select name="unit" id="waste-unit" class="select w-full min-h-11 text-sm rounded-xl">
                    <option value="base">Satuan dasar</option>
                    <option value="purchase">Satuan beli</option>
                </select>
            </x-field>
        </div>
        <x-field label="Alasan" name="reason">
            <select name="reason" required class="select w-full min-h-11 text-sm rounded-xl">
                @foreach($reasons as $reason)
                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Catatan (opsional)" name="note">
            <input type="text" name="note" maxlength="500" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
            <button type="submit" class="btn btn-primary min-h-11" data-requires-online>Simpan</button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
    const wasteIngredient = document.getElementById('waste-ingredient');
    const wasteUnit = document.getElementById('waste-unit');

    wasteIngredient.addEventListener('change', () => {
        const option = wasteIngredient.selectedOptions[0];
        wasteUnit.options[0].textContent = option?.dataset.base ? `Satuan dasar (${option.dataset.base})` : 'Satuan dasar';
        wasteUnit.options[1].textContent = option?.dataset.purchase ? `Satuan beli (${option.dataset.purchase})` : 'Satuan beli';
    });
</script>
@endpush
