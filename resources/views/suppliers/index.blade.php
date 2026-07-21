@extends('layouts.app')

@section('title', 'Manajemen Supplier')

@section('content')
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Manajemen Supplier</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Kelola daftar pemasok bahan baku, kontak PIC, dan riwayat item yang dipasok.</p>
    </div>
    <button onclick="document.getElementById('modal-add-supplier').showModal()" class="btn btn-primary text-white btn-sm flex items-center gap-2 px-5 py-2 h-auto shadow-md">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Tambah Supplier Baru
    </button>
</header>

<!-- Search Bar -->
<div class="card-premium p-4 shadow-xs">
    <form method="GET" action="{{ route('suppliers.index') }}" class="flex gap-3">
        <div class="flex-1 relative">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama supplier, PIC, email..." class="input w-full pl-10 pr-4 py-2.5 text-xs rounded-xl font-medium" />
            <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3"></i>
        </div>
        <button type="submit" class="btn btn-neutral text-white btn-sm px-6 py-2.5 h-auto rounded-xl font-bold">Cari</button>
        @if(request('search'))
            <a href="{{ route('suppliers.index') }}" class="btn btn-ghost btn-sm text-gray-500 font-semibold">Reset</a>
        @endif
    </form>
</div>

<!-- Table Suppliers -->
<div class="card-premium overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>Nama Supplier</th>
                    <th>Contact Person</th>
                    <th>Telepon / HP</th>
                    <th>Email</th>
                    <th>Alamat</th>
                    <th class="text-center">Item Dipasok</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suppliers as $sup)
                <tr>
                    <td class="font-extrabold text-neutral text-sm">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-primary/10 text-primary flex items-center justify-center text-xs font-black shadow-xs border border-primary/20">
                                {{ strtoupper(substr($sup->name, 0, 1)) }}
                            </div>
                            <span>{{ $sup->name }}</span>
                        </div>
                    </td>
                    <td class="text-xs text-gray-700 font-bold">{{ $sup->contact_person ?? '-' }}</td>
                    <td class="text-xs font-mono font-bold text-neutral">{{ $sup->phone ?? '-' }}</td>
                    <td class="text-xs text-gray-500 font-medium">{{ $sup->email ?? '-' }}</td>
                    <td class="text-xs text-gray-500 max-w-xs truncate font-medium">{{ $sup->address ?? '-' }}</td>
                    <td class="text-center">
                        <span class="badge badge-neutral text-xs font-semibold">{{ $sup->items_count }} item</span>
                    </td>
                    <td class="text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="editSupplier({{ json_encode($sup) }})" title="Edit Supplier" class="btn-action-edit">
                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                            </button>
                            <form action="{{ route('suppliers.destroy', $sup->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus supplier ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Hapus Supplier" class="btn-action-delete">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400 text-xs font-medium">
                        <i data-lucide="truck" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
                        Belum ada data supplier terdaftar.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($suppliers->hasPages())
    <div class="p-4 border-t border-base-200">
        {{ $suppliers->withQueryString()->links() }}
    </div>
    @endif
</div>

<!-- Modal Add Supplier -->
<dialog id="modal-add-supplier" class="modal backdrop-blur-xs">
    <div class="modal-box max-w-md bg-base-100 border border-base-300 p-0 shadow-2xl rounded-2xl overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-neutral tracking-tight">Tambah Supplier Baru</h3>
                    <p class="text-[11px] text-gray-500 font-medium">Lengkapi rincian kontak dan informasi pemasok.</p>
                </div>
            </div>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost text-gray-400 hover:text-neutral">✕</button>
            </form>
        </div>

        <!-- Modal Body & Form -->
        <form method="POST" action="{{ route('suppliers.store') }}" class="p-6 space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Nama Supplier / PT / CV *</label>
                <input type="text" name="name" required placeholder="PT Sumber Sejahtera" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Contact Person (PIC)</label>
                <input type="text" name="contact_person" placeholder="Nama Sales / PIC" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">No. Telepon / WhatsApp</label>
                    <input type="text" name="phone" placeholder="0812xxxx" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Email Supplier</label>
                    <input type="email" name="email" placeholder="sales@company.com" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Alamat Lengkap</label>
                <textarea name="address" rows="3" placeholder="Alamat kantor / gudang supplier" class="textarea textarea-bordered w-full px-3 py-2 text-xs rounded-xl font-medium focus:outline-none"></textarea>
            </div>
            <div class="modal-action border-t border-base-200 pt-4 mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-supplier').close()" class="btn btn-ghost btn-sm text-gray-500 rounded-xl font-semibold">Batal</button>
                <button type="submit" class="btn btn-primary text-white btn-sm px-6 rounded-xl font-bold">Simpan Supplier</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Edit Supplier -->
<dialog id="modal-edit-supplier" class="modal backdrop-blur-xs">
    <div class="modal-box max-w-md bg-base-100 border border-base-300 p-0 shadow-2xl rounded-2xl overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-neutral tracking-tight">Edit Data Supplier</h3>
                    <p class="text-[11px] text-gray-500 font-medium">Ubah rincian kontak dan informasi pemasok.</p>
                </div>
            </div>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost text-gray-400 hover:text-neutral">✕</button>
            </form>
        </div>

        <!-- Modal Body & Form -->
        <form id="form-edit-supplier" method="POST" action="" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Nama Supplier *</label>
                <input type="text" id="edit-sup-name" name="name" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Contact Person (PIC)</label>
                <input type="text" id="edit-sup-cp" name="contact_person" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">No. Telepon</label>
                    <input type="text" id="edit-sup-phone" name="phone" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Email</label>
                    <input type="email" id="edit-sup-email" name="email" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Alamat</label>
                <textarea id="edit-sup-address" name="address" rows="3" class="textarea textarea-bordered w-full px-3 py-2 text-xs rounded-xl font-medium focus:outline-none"></textarea>
            </div>
            <div class="modal-action border-t border-base-200 pt-4 mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-edit-supplier').close()" class="btn btn-ghost btn-sm text-gray-500 rounded-xl font-semibold">Batal</button>
                <button type="submit" class="btn btn-primary text-white btn-sm px-6 rounded-xl font-bold">Update Supplier</button>
            </div>
        </form>
    </div>
</dialog>

@push('scripts')
<script>
    function editSupplier(sup) {
        const form = document.getElementById('form-edit-supplier');
        form.action = `/suppliers/${sup.id}`;
        document.getElementById('edit-sup-name').value = sup.name;
        document.getElementById('edit-sup-cp').value = sup.contact_person || '';
        document.getElementById('edit-sup-phone').value = sup.phone || '';
        document.getElementById('edit-sup-email').value = sup.email || '';
        document.getElementById('edit-sup-address').value = sup.address || '';
        document.getElementById('modal-edit-supplier').showModal();
    }
</script>
@endpush
@endsection
