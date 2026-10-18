@extends('layouts.app')

@section('title', 'Supplier')

@section('content')
<x-page-header title="Supplier" subtitle="Pemasok bahan baku untuk pencatatan stok masuk.">
    <x-slot:actions>
        <button type="button" onclick="document.getElementById('modal-add').showModal()" class="btn btn-primary btn-sm min-h-11 gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> Tambah Supplier
        </button>
    </x-slot:actions>
</x-page-header>

<div class="card-premium overflow-hidden">
    <table class="table-premium">
        <thead>
            <tr><th>Nama</th><th>Kontak</th><th></th></tr>
        </thead>
        <tbody>
            @forelse($suppliers as $supplier)
                <tr>
                    <td class="font-extrabold text-neutral">{{ $supplier->name }}</td>
                    <td class="text-xs">{{ $supplier->contact ?? '-' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button type="button" class="btn-action-edit" aria-label="Ubah {{ $supplier->name }}"
                            onclick="editSupplier({{ \Illuminate\Support\Js::from($supplier->only(['id', 'name', 'contact'])) }})">
                            <i data-lucide="edit-2" class="w-4 h-4"></i>
                        </button>
                        <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="inline" data-confirm="Hapus supplier {{ $supplier->name }}?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-action-delete" aria-label="Hapus {{ $supplier->name }}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center py-12 text-xs text-base-content/50">Belum ada supplier.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($suppliers->hasPages())
        <div class="p-4 border-t border-base-200">{{ $suppliers->links() }}</div>
    @endif
</div>

<x-modal id="modal-add" title="Tambah Supplier">
    <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-3">
        @csrf
        <x-field label="Nama" name="name"><input type="text" name="name" required maxlength="120" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
        <x-field label="Kontak" name="contact"><input type="text" name="contact" maxlength="255" placeholder="Telepon / WhatsApp" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
            <button type="submit" class="btn btn-primary min-h-11">Simpan</button>
        </div>
    </form>
</x-modal>

<x-modal id="modal-edit" title="Ubah Supplier">
    <form id="form-edit" method="POST" action="" class="space-y-3">
        @csrf @method('PUT')
        <x-field label="Nama" name="name"><input type="text" name="name" required maxlength="120" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
        <x-field label="Kontak" name="contact"><input type="text" name="contact" maxlength="255" class="input w-full min-h-11 text-sm rounded-xl" /></x-field>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
            <button type="submit" class="btn btn-primary min-h-11">Simpan</button>
        </div>
    </form>
</x-modal>
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

    function editSupplier(supplier) {
        const form = document.getElementById('form-edit');
        form.action = `{{ url('suppliers') }}/${supplier.id}`;
        form.querySelector('[name=name]').value = supplier.name;
        form.querySelector('[name=contact]').value = supplier.contact ?? '';
        document.getElementById('modal-edit').showModal();
    }
</script>
@endpush
