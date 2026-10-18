@php($withActive = $withActive ?? false)

<x-field label="Nama bahan" name="name">
    <input type="text" name="name" required maxlength="120" class="input w-full min-h-11 text-sm rounded-xl" />
</x-field>

<div class="grid grid-cols-2 gap-3">
    <x-field label="Kategori" name="category_name" hint="Ketik baru atau pilih yang ada.">
        <input type="text" name="category_name" list="{{ $prefix }}-categories" maxlength="80" class="input w-full min-h-11 text-sm rounded-xl" />
        <datalist id="{{ $prefix }}-categories">
            @foreach($categories as $category)
                <option value="{{ $category->name }}"></option>
            @endforeach
        </datalist>
    </x-field>
    <x-field label="Satuan dasar" name="base_unit">
        <select name="base_unit" class="select w-full min-h-11 text-sm rounded-xl" required>
            @foreach($units as $unit)
                <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
            @endforeach
        </select>
    </x-field>
</div>

<div class="grid grid-cols-2 gap-3">
    <x-field label="Satuan beli" name="purchase_unit" hint="mis. karton, botol, pack">
        <input type="text" name="purchase_unit" maxlength="40" class="input w-full min-h-11 text-sm rounded-xl" />
    </x-field>
    <x-field label="Isi per satuan beli" name="purchase_unit_qty" hint="Dalam satuan dasar, mis. 12000 ml">
        <input type="number" name="purchase_unit_qty" value="1" min="0.001" step="any" required class="input w-full min-h-11 text-sm rounded-xl" />
    </x-field>
</div>

<x-field label="Stok minimum" name="min_stock" hint="Dalam satuan dasar.">
    <input type="number" name="min_stock" value="0" min="0" step="any" required class="input w-full min-h-11 text-sm rounded-xl" />
</x-field>

@if($withActive)
    <label class="flex items-center gap-2 text-xs font-bold min-h-11">
        <input type="checkbox" name="is_active" value="1" class="checkbox checkbox-sm checkbox-primary" />
        Aktif
    </label>
@endif
