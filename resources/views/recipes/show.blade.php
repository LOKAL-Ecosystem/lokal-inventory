@extends('layouts.app')

@section('title', 'Resep ' . $product->name)

@section('content')
<x-page-header :title="$product->name" :subtitle="'Harga jual Rp ' . number_format($product->price, 0, ',', '.') . ($product->category ? ' · ' . $product->category : '')">
    <x-slot:actions>
        <a href="{{ route('recipes.index') }}" class="btn btn-ghost btn-sm min-h-11 gap-2"><i data-lucide="arrow-left" class="w-4 h-4"></i> Kembali</a>
    </x-slot:actions>
</x-page-header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">HPP</div>
        <div class="text-2xl font-extrabold mt-1" id="hpp-value">Rp {{ number_format($summary['hpp'], 0, ',', '.') }}</div>
    </div>
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Margin</div>
        <div class="text-2xl font-extrabold mt-1 {{ $summary['margin'] < 0 ? 'text-error' : 'text-success' }}">Rp {{ number_format($summary['margin'], 0, ',', '.') }}</div>
    </div>
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Margin %</div>
        <div class="text-2xl font-extrabold mt-1">{{ $summary['margin_pct'] !== null ? $summary['margin_pct'] . '%' : '-' }}</div>
    </div>
    <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Porsi tersedia</div>
        <div class="text-2xl font-extrabold mt-1">{{ $portions ?? '-' }}</div>
    </div>
</section>

{{-- Resep dasar --}}
<form method="POST" action="{{ route('recipes.update', $product->pos_product_id) }}" class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    @csrf @method('PUT')
    <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Resep dasar (1 porsi)</h2>
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50"><tr><th class="min-w-56">Bahan</th><th class="min-w-40">Takaran</th><th class="text-right">Biaya</th><th></th></tr></thead>
            <tbody id="recipe-rows"></tbody>
        </table>
    </div>
    <div class="p-4 border-t border-base-200 flex justify-between items-center">
        <button type="button" id="add-item" class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah bahan</button>
        <button type="submit" class="btn btn-primary min-h-11">Simpan Resep</button>
    </div>
</form>

{{-- Penyesuaian per opsi kustomisasi --}}
<section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Penyesuaian per opsi kustomisasi</h2>
    <p class="px-5 pt-3 text-[11px] text-base-content/60">Urutan penerapan: ganti bahan → tambah bahan → kalikan takaran.</p>

    @forelse($modifiers as $groupId => $groupModifiers)
        <div class="px-5 py-3 border-b border-base-200 last:border-0">
            <div class="text-[11px] font-extrabold uppercase tracking-wider text-base-content/50 mb-2">{{ $groups[$groupId]->name ?? 'Opsi' }}</div>
            <div class="space-y-2">
                @foreach($groupModifiers as $modifier)
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="flex items-center gap-2 min-w-40 font-bold text-sm">
                            <input type="checkbox" class="checkbox checkbox-sm checkbox-primary preview-modifier" value="{{ $modifier->pos_modifier_id }}" />
                            {{ $modifier->name }}
                        </label>

                        @foreach($adjustments[$modifier->pos_modifier_id] ?? [] as $adjustment)
                            <span class="badge badge-neutral gap-1 py-3">
                                @switch($adjustment->action->value)
                                    @case('add') + {{ $adjustment->ingredient->formatQty($adjustment->qty) }} {{ $adjustment->ingredient->name }} @break
                                    @case('replace') {{ $adjustment->replacesIngredient->name }} → {{ $adjustment->ingredient->name }}@if($adjustment->qty) ({{ $adjustment->ingredient->formatQty($adjustment->qty) }})@endif @break
                                    @case('multiply') × {{ rtrim(rtrim(number_format($adjustment->factor, 4, ',', '.'), '0'), ',') }} {{ $adjustment->ingredient?->name ?? 'semua bahan' }} @break
                                @endswitch
                                <form method="POST" action="{{ route('recipes.adjustments.destroy', $adjustment) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" aria-label="Hapus penyesuaian" class="ml-1 text-base-content/60 hover:text-error"><i data-lucide="x" class="w-3 h-3"></i></button>
                                </form>
                            </span>
                        @endforeach

                        <button type="button" class="btn btn-ghost btn-xs min-h-9 gap-1 ml-auto"
                            onclick="openAdjustment({{ $modifier->pos_modifier_id }}, @js($modifier->name))">
                            <i data-lucide="plus" class="w-3 h-3"></i> Penyesuaian
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="text-center py-10 text-xs text-base-content/50">Menu ini tidak punya opsi kustomisasi (atau katalog belum disinkronkan).</p>
    @endforelse

    <div id="preview" class="hidden px-5 py-4 bg-base-200/60 border-t border-base-200 text-xs">
        <div class="font-extrabold mb-1">Simulasi pemakaian untuk opsi yang dicentang</div>
        <ul id="preview-usage" class="list-disc pl-5 space-y-0.5"></ul>
        <div class="mt-2 font-extrabold">HPP kombinasi: <span id="preview-hpp"></span></div>
    </div>
</section>

<x-modal id="modal-adjustment" title="Penyesuaian Opsi">
    <form method="POST" action="{{ route('recipes.adjustments.store', $product->pos_product_id) }}" class="space-y-3" id="adjustment-form">
        @csrf
        <input type="hidden" name="pos_modifier_id" id="adj-modifier" />
        <p class="text-xs font-bold text-base-content/70">Opsi: <span id="adj-modifier-name"></span></p>

        <x-field label="Aksi" name="action">
            <select name="action" id="adj-action" class="select w-full min-h-11 text-sm rounded-xl">
                @foreach($actions as $action)
                    <option value="{{ $action->value }}">{{ $action->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field label="Bahan yang diganti" name="replaces_ingredient_id" data-for="replace" class="adj-field" style="display:none">
            <select name="replaces_ingredient_id" class="select w-full min-h-11 text-sm rounded-xl">
                <option value="">Pilih bahan…</option>
                @foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>@endforeach
            </select>
        </x-field>

        <x-field label="Bahan" name="ingredient_id" hint="Untuk 'kalikan': kosongkan = semua bahan resep." data-for="add replace multiply" class="adj-field">
            <select name="ingredient_id" class="select w-full min-h-11 text-sm rounded-xl">
                <option value="">— semua bahan —</option>
                @foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>@endforeach
            </select>
        </x-field>

        <x-field label="Takaran" name="qty" hint="Satuan dasar. Pada 'ganti', kosong = sama dengan takaran bahan yang diganti." data-for="add replace" class="adj-field">
            <input type="number" name="qty" min="0.001" step="any" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>

        <x-field label="Faktor pengali" name="factor" hint="mis. 1.3 untuk ukuran besar." data-for="multiply" class="adj-field" style="display:none">
            <input type="number" name="factor" min="0.0001" step="any" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>

        <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick="this.closest('dialog').close()" class="btn btn-ghost min-h-11">Batal</button>
            <button type="submit" class="btn btn-primary min-h-11">Simpan</button>
        </div>
    </form>
</x-modal>
@endsection

@php
    $ingredientOptions = $ingredients->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->base_unit->value, 'cost' => (float) $i->avg_cost])->values();
    $existingItems = old('items', $items->map(fn ($i) => ['ingredient_id' => $i->ingredient_id, 'qty' => (float) $i->qty])->values()->all());
    $canViewCosts = auth()->user()->can('view-costs');
@endphp

@push('scripts')
<script>
    const allIngredients = @json($ingredientOptions);
    const existing = @json($existingItems);
    const showCosts = @json($canViewCosts);
    const body = document.getElementById('recipe-rows');
    let n = 0;

    function addItem(values = {}) {
        const i = n++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="items[${i}][ingredient_id]" required class="select w-full min-h-11 text-sm rounded-xl">
                <option value="">Pilih bahan…</option>
                ${allIngredients.map((x) => `<option value="${x.id}">${x.name}</option>`).join('')}
            </select></td>
            <td><div class="flex items-center gap-2">
                <input type="number" name="items[${i}][qty]" min="0.001" step="any" required class="input w-full min-h-11 text-sm rounded-xl" />
                <span class="text-xs font-bold text-base-content/60 unit"></span></div></td>
            <td class="text-right text-xs font-bold cost">-</td>
            <td><button type="button" class="btn btn-ghost btn-square btn-sm text-error hover:bg-error/10 remove" aria-label="Hapus bahan"><i data-lucide="trash-2" class="w-4 h-4"></i></button></td>`;
        body.appendChild(tr);

        const select = tr.querySelector('select');
        const qty = tr.querySelector('input');
        select.value = values.ingredient_id ?? '';
        qty.value = values.qty ?? '';

        const refresh = () => {
            const item = allIngredients.find((x) => String(x.id) === select.value);
            tr.querySelector('.unit').textContent = item?.unit ?? '';
            tr.querySelector('.cost').textContent = item && qty.value && showCosts
                ? 'Rp ' + (item.cost * qty.value).toLocaleString('id-ID', { maximumFractionDigits: 0 }) : '-';
        };
        [select, qty].forEach((el) => el.addEventListener('input', refresh));
        tr.querySelector('.remove').addEventListener('click', () => tr.remove());
        refresh();
        window.initLucideIcons?.();
    }

    document.getElementById('add-item').addEventListener('click', () => addItem());
    existing.forEach((row) => addItem(row));

    // Modal penyesuaian
    const action = document.getElementById('adj-action');
    function syncAdjustmentFields() {
        document.querySelectorAll('#adjustment-form .adj-field').forEach((field) => {
            field.style.display = field.dataset.for.split(' ').includes(action.value) ? '' : 'none';
        });
    }
    action.addEventListener('change', syncAdjustmentFields);
    function openAdjustment(id, name) {
        document.getElementById('adj-modifier').value = id;
        document.getElementById('adj-modifier-name').textContent = name;
        syncAdjustmentFields();
        document.getElementById('modal-adjustment').showModal();
    }

    // Simulasi pemakaian
    const previewBox = document.getElementById('preview');
    async function runPreview() {
        const ids = [...document.querySelectorAll('.preview-modifier:checked')].map((c) => `modifier_ids[]=${c.value}`);
        const response = await fetch(`{{ route('recipes.preview', $product->pos_product_id) }}?${ids.join('&')}`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        previewBox.classList.toggle('hidden', ids.length === 0);
        document.getElementById('preview-usage').innerHTML = data.usage.map((u) => `<li>${u.ingredient}: ${u.qty}</li>`).join('');
        document.getElementById('preview-hpp').textContent = showCosts ? 'Rp ' + data.hpp.toLocaleString('id-ID') : '-';
    }
    document.querySelectorAll('.preview-modifier').forEach((c) => c.addEventListener('change', runPreview));
</script>
@endpush
