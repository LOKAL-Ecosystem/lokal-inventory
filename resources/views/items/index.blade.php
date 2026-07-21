@extends('layouts.app')

@section('title', 'Master Data Stok Item')

@section('content')
<!-- Header -->
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-base-300/60 pb-5">
    <div>
        <h1 class="text-2xl font-extrabold text-neutral tracking-tight">Master Data Bahan & Produk</h1>
        <p class="text-xs text-gray-500 font-medium mt-1">Kelola item stok, satuan, kategori, threshold minimum, dan link POS Product ID.</p>
    </div>
    <button onclick="document.getElementById('modal-add-item').showModal()" class="btn btn-primary text-white btn-sm flex items-center gap-2 px-5 py-2 h-auto shadow-md">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Tambah Item Baru
    </button>
</header>

<!-- Filters & Search Bar -->
<div class="card-premium p-4 shadow-xs">
    <form method="GET" action="{{ route('items.index') }}" class="flex flex-col sm:flex-row gap-3">
        <div class="flex-1 relative">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari berdasarkan SKU, Nama Item, POS Product ID..." class="input w-full pl-10 pr-4 py-2.5 text-xs rounded-xl font-medium" />
            <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3.5 top-3"></i>
        </div>
        <div class="w-full sm:w-56">
            <select name="category_id" class="select w-full px-3.5 py-2.5 text-xs rounded-xl font-medium" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-neutral text-white btn-sm px-6 py-2.5 h-auto rounded-xl font-bold">Filter</button>
            @if(request()->hasAny(['search', 'category_id', 'low_stock']))
                <a href="{{ route('items.index') }}" class="btn btn-ghost btn-sm rounded-xl text-gray-500 font-semibold">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Table Items -->
<div class="card-premium overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th>SKU / POS ID</th>
                    <th>Nama Item Stok</th>
                    <th>Kategori</th>
                    <th>Satuan</th>
                    <th class="text-right">Stok Fisik</th>
                    <th class="text-right">Min Threshold</th>
                    <th class="text-right">Harga Beli / Unit</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>
                        <span class="code-chip">{{ $item->sku }}</span>
                        @if($item->pos_product_id)
                            <div class="text-[11px] text-primary flex items-center gap-1 font-mono font-bold mt-1.5">
                                <i data-lucide="link-2" class="w-3.5 h-3.5"></i> POS: {{ $item->pos_product_id }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="font-extrabold text-neutral text-sm tracking-tight">{{ $item->name }}</div>
                        <div class="text-[11px] text-gray-400 font-semibold mt-0.5">Supplier: {{ $item->supplier?->name ?? '-' }}</div>
                    </td>
                    <td><span class="badge badge-neutral text-xs font-semibold">{{ $item->category?->name ?? 'Uncategorized' }}</span></td>
                    <td class="font-extrabold text-xs text-neutral">{{ $item->unit?->symbol ?? '-' }}</td>
                    <td class="text-right font-black text-base {{ $item->isLowStock() ? 'text-error' : 'text-neutral' }}">
                        {{ number_format($item->quantity_on_hand, 2) }}
                    </td>
                    <td class="text-right text-xs text-gray-400 font-bold">
                        {{ number_format($item->minimum_stock, 2) }}
                    </td>
                    <td class="text-right text-xs font-extrabold text-neutral">
                        Rp {{ number_format($item->unit_cost, 0, ',', '.') }}
                    </td>
                    <td class="text-center">
                        @if($item->isLowStock())
                            <span class="badge badge-error">Low Stock</span>
                        @else
                            <span class="badge badge-success">Aman</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="editItem({{ json_encode($item) }})" title="Edit Item" class="btn-action-edit">
                                <i data-lucide="edit-2" class="w-4 h-4"></i>
                            </button>
                            <form action="{{ route('items.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus item ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Hapus Item" class="btn-action-delete">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-12 text-gray-400 text-xs font-medium">
                        <i data-lucide="package-open" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
                        Tidak ada data item stok ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
    <div class="p-4 border-t border-base-200">
        {{ $items->withQueryString()->links() }}
    </div>
    @endif
</div>

<!-- Modal Add Item -->
<dialog id="modal-add-item" class="modal backdrop-blur-xs">
    <div class="modal-box max-w-lg bg-base-100 border border-base-300 p-0 shadow-2xl rounded-2xl overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <i data-lucide="package-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-neutral tracking-tight">Tambah Item Stok Baru</h3>
                    <p class="text-[11px] text-gray-500 font-medium">Lengkapi detail barang/bahan baku baru di bawah ini.</p>
                </div>
            </div>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost text-gray-400 hover:text-neutral">✕</button>
            </form>
        </div>

        <!-- Modal Body & Form -->
        <form method="POST" action="{{ route('items.store') }}" class="p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Kode SKU *</label>
                    <input type="text" name="sku" required placeholder="e.g. RAW-KOP-001" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">POS Product ID / Code</label>
                    <input type="text" name="pos_product_id" placeholder="ID di Lokal-POS" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Nama Item Stok *</label>
                <input type="text" name="name" required placeholder="Nama bahan baku / produk" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Kategori</label>
                    <select name="category_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="">Pilih Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Satuan (Unit)</label>
                    <select name="unit_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="">Pilih Satuan</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Supplier Utama</label>
                <select name="supplier_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                    <option value="">Pilih Supplier</option>
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Stok Awal</label>
                    <input type="number" step="0.01" name="quantity_on_hand" value="0" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Min Threshold</label>
                    <input type="number" step="0.01" name="minimum_stock" value="10" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Harga Beli / Unit</label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 inset-y-0 flex items-center text-xs font-bold text-gray-500 z-10 pointer-events-none">Rp</span>
                        <input type="text" id="add-unit-cost-display" oninput="onModalCostInput(this, 'add-unit-cost')" value="0" placeholder="0" class="input input-bordered w-full pl-9 pr-3 py-2 text-xs rounded-xl font-bold" />
                        <input type="hidden" name="unit_cost" id="add-unit-cost" value="0" />
                    </div>
                </div>
            </div>

            <div class="modal-action border-t border-base-200 pt-4 mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-add-item').close()" class="btn btn-ghost btn-sm text-gray-500 rounded-xl font-semibold">Batal</button>
                <button type="submit" class="btn btn-primary text-white btn-sm px-6 rounded-xl font-bold">Simpan Item</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Modal Edit Item -->
<dialog id="modal-edit-item" class="modal backdrop-blur-xs">
    <div class="modal-box max-w-lg bg-base-100 border border-base-300 p-0 shadow-2xl rounded-2xl overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-base-200 bg-base-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-extrabold text-base text-neutral tracking-tight">Edit Item Stok</h3>
                    <p class="text-[11px] text-gray-500 font-medium">Ubah informasi detail barang atau stok minimal.</p>
                </div>
            </div>
            <form method="dialog">
                <button class="btn btn-sm btn-circle btn-ghost text-gray-400 hover:text-neutral">✕</button>
            </form>
        </div>

        <!-- Modal Body & Form -->
        <form id="form-edit-item" method="POST" action="" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Kode SKU *</label>
                    <input type="text" id="edit-sku" name="sku" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">POS Product ID</label>
                    <input type="text" id="edit-pos-product-id" name="pos_product_id" class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-bold text-neutral">Nama Item *</label>
                <input type="text" id="edit-name" name="name" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Kategori</label>
                    <select id="edit-category-id" name="category_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="">Pilih Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Satuan (Unit)</label>
                    <select id="edit-unit-id" name="unit_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="">Pilih Satuan</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Supplier Utama</label>
                    <select id="edit-supplier-id" name="supplier_id" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="">Pilih Supplier</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Status Aktif</label>
                    <select id="edit-is-active" name="is_active" class="select select-bordered w-full px-3 py-2 text-xs rounded-xl font-bold">
                        <option value="1">Aktif</option>
                        <option value="0">Non-Aktif</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Minimum Threshold</label>
                    <input type="number" step="0.01" id="edit-minimum-stock" name="minimum_stock" required class="input input-bordered w-full px-3 py-2 text-xs rounded-xl font-bold" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-neutral">Harga Beli / Unit (Rp)</label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3 inset-y-0 flex items-center text-xs font-bold text-gray-500 z-10 pointer-events-none">Rp</span>
                        <input type="text" id="edit-unit-cost-display" oninput="onModalCostInput(this, 'edit-unit-cost')" value="0" placeholder="0" class="input input-bordered w-full pl-9 pr-3 py-2 text-xs rounded-xl font-bold" />
                        <input type="hidden" name="unit_cost" id="edit-unit-cost" value="0" />
                    </div>
                </div>
            </div>

            <div class="modal-action border-t border-base-200 pt-4 mt-6 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('modal-edit-item').close()" class="btn btn-ghost btn-sm text-gray-500 rounded-xl font-semibold">Batal</button>
                <button type="submit" class="btn btn-primary text-white btn-sm px-6 rounded-xl font-bold">Update Item</button>
            </div>
        </form>
    </div>
</dialog>

@push('scripts')
<script>
    function onModalCostInput(inputElem, hiddenInputId) {
        let rawValue = inputElem.value.replace(/[^0-9]/g, '');
        let numValue = parseInt(rawValue, 10) || 0;
        document.getElementById(hiddenInputId).value = numValue;
        inputElem.value = numValue > 0 ? numValue.toLocaleString('id-ID') : '0';
    }

    function editItem(item) {
        const form = document.getElementById('form-edit-item');
        form.action = `/items/${item.id}`;
        document.getElementById('edit-sku').value = item.sku;
        document.getElementById('edit-name').value = item.name;
        document.getElementById('edit-pos-product-id').value = item.pos_product_id || '';
        document.getElementById('edit-category-id').value = item.category_id || '';
        document.getElementById('edit-unit-id').value = item.unit_id || '';
        document.getElementById('edit-supplier-id').value = item.supplier_id || '';
        document.getElementById('edit-minimum-stock').value = item.minimum_stock;
        
        const cost = parseInt(item.unit_cost) || 0;
        document.getElementById('edit-unit-cost').value = cost;
        document.getElementById('edit-unit-cost-display').value = cost > 0 ? cost.toLocaleString('id-ID') : '0';
        
        document.getElementById('edit-is-active').value = item.is_active ? 1 : 0;
        document.getElementById('modal-edit-item').showModal();
    }
</script>
@endpush
@endsection
