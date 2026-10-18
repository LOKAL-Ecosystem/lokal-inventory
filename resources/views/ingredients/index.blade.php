@extends('layouts.app')

@section('title', 'Bahan Baku')

@section('content')
<x-page-header title="Bahan Baku" subtitle="Stok saat ini dihitung dari ledger pergerakan. Stok negatif ditandai merah.">
    <x-slot:actions>
        @can('manage-inventory')
            <button type="button" onclick="document.getElementById('modal-add').showModal()" class="btn btn-primary btn-sm min-h-11 gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i> Tambah Bahan
            </button>
        @endcan
    </x-slot:actions>
</x-page-header>

<form method="GET" class="bg-base-100 border border-base-300 rounded-2xl p-4 flex flex-col sm:flex-row gap-3">
    <input type="search" name="search" value="{{ request('search') }}" placeholder="Cari bahan…" autofocus class="input flex-1 min-h-11 text-sm rounded-xl" />
    <select name="category" class="select min-h-11 text-sm rounded-xl sm:w-56" onchange="this.form.submit()">
        <option value="">Semua kategori</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected(request('category') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2 text-xs font-bold min-h-11">
        <input type="checkbox" name="low" value="1" class="checkbox checkbox-sm checkbox-primary" @checked(request()->boolean('low')) onchange="this.form.submit()" />
        Hampir habis
    </label>
    <button class="btn btn-neutral min-h-11">Cari</button>
</form>

<div class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                <tr>
                    <th>Bahan</th>
                    <th>Kategori</th>
                    <th class="text-right">Stok</th>
                    <th class="text-right">Minimum</th>
                    <th>Satuan beli</th>
                    @can('view-costs')<th class="text-right">Biaya rata-rata</th>@endcan
                    <th class="text-center">Status</th>
                    @can('manage-inventory')<th></th>@endcan
                </tr>
            </thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($ingredients as $item)
                    <tr class="{{ $item->is_active ? '' : 'opacity-50' }}">
                        <td><a href="{{ route('ingredients.show', $item) }}" class="font-extrabold text-neutral hover:text-primary">{{ $item->name }}</a></td>
                        <td class="text-xs">{{ $item->category?->name ?? '-' }}</td>
                        <td class="text-right font-black {{ $item->isNegative() ? 'text-error' : ($item->isLow() ? 'text-warning' : 'text-neutral') }}">{{ $item->formatQty($item->current_stock) }}</td>
                        <td class="text-right text-xs text-base-content/60">{{ $item->formatQty($item->min_stock) }}</td>
                        <td class="text-xs">
                            @if($item->purchase_unit) 1 {{ $item->purchase_unit }} = {{ $item->formatQty($item->purchase_unit_qty) }} @else - @endif
                        </td>
                        @can('view-costs')
                            <td class="text-right text-xs font-bold">Rp {{ rtrim(rtrim(number_format($item->avg_cost, 4, ',', '.'), '0'), ',') }} / {{ $item->base_unit->value }}</td>
                        @endcan
                        <td class="text-center">
                            @if(! $item->is_active) <span class="badge badge-neutral">Nonaktif</span>
                            @elseif($item->isNegative()) <span class="badge badge-error">Negatif</span>
                            @elseif($item->isLow()) <span class="badge badge-warning">Hampir habis</span>
                            @else <span class="badge badge-success">Aman</span> @endif
                        </td>
                        @can('manage-inventory')
                            @php
                                $editData = [
                                    'id' => $item->id,
                                    'name' => $item->name,
                                    'base_unit' => $item->base_unit->value,
                                    'purchase_unit' => $item->purchase_unit,
                                    'purchase_unit_qty' => $item->purchase_unit_qty,
                                    'min_stock' => $item->min_stock,
                                    'is_active' => $item->is_active,
                                    'category_name' => $item->category?->name,
                                ];
                            @endphp
                            <td class="text-right whitespace-nowrap">
                                <button type="button" class="btn btn-ghost btn-square btn-sm text-primary hover:bg-primary/10" aria-label="Ubah {{ $item->name }}"
                                    onclick="editIngredient({{ \Illuminate\Support\Js::from($editData) }})">
                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                </button>
                                <form action="{{ route('ingredients.destroy', $item) }}" method="POST" class="inline" data-confirm="Hapus / nonaktifkan {{ $item->name }}?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-square btn-sm text-error hover:bg-error/10" aria-label="Hapus {{ $item->name }}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                </form>
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-12 text-xs text-base-content/50">Belum ada bahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($ingredients->hasPages())
        <div class="p-4 border-t border-base-200">{{ $ingredients->links() }}</div>
    @endif
</div>

@can('manage-inventory')
    <x-modal id="modal-add" title="Tambah Bahan">
        <form method="POST" action="{{ route('ingredients.store') }}" class="space-y-3">
            @csrf
            @include('ingredients._form', ['prefix' => 'add'])
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
                <button type="submit" class="btn btn-primary min-h-11">Simpan</button>
            </div>
        </form>
    </x-modal>

    <x-modal id="modal-edit" title="Ubah Bahan">
        <form id="form-edit" method="POST" action="" class="space-y-3">
            @csrf @method('PUT')
            @include('ingredients._form', ['prefix' => 'edit', 'withActive' => true])
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
                <button type="submit" class="btn btn-primary min-h-11">Simpan</button>
            </div>
        </form>
    </x-modal>
@endcan
@endsection

@push('scripts')
<script>
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            Swal.fire({ title: form.dataset.confirm, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya', cancelButtonText: 'Batal' })
                .then((result) => result.isConfirmed && form.submit());
        });
    });

    function editIngredient(item) {
        const form = document.getElementById('form-edit');
        form.action = `{{ url('ingredients') }}/${item.id}`;
        form.querySelector('[name=name]').value = item.name;
        form.querySelector('[name=category_name]').value = item.category_name ?? '';
        form.querySelector('[name=base_unit]').value = item.base_unit;
        form.querySelector('[name=purchase_unit]').value = item.purchase_unit ?? '';
        form.querySelector('[name=purchase_unit_qty]').value = parseFloat(item.purchase_unit_qty);
        form.querySelector('[name=min_stock]').value = parseFloat(item.min_stock);
        form.querySelector('[name=is_active]').checked = !!item.is_active;
        document.getElementById('modal-edit').showModal();
    }
</script>
@endpush
