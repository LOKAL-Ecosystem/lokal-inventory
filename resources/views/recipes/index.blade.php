@extends('layouts.app')

@section('title', 'Manajemen Resep / BOM')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-neutral tracking-tight flex items-center gap-2.5">
                <i data-lucide="chef-hat" class="w-7 h-7 text-primary"></i>
                Manajemen Resep (Bill of Materials)
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-1">
                Daftar resep bahan baku dasar dan penyesuaian modifier untuk tiap produk POS.
            </p>
        </div>
        <div>
            <a href="{{ route('webhooks.unmapped') }}" class="btn btn-sm btn-warning text-white rounded-xl font-bold gap-2">
                <i data-lucide="file-question" class="w-4 h-4"></i> Produk Unmapped
            </a>
        </div>
    </div>

    <!-- Recipes Table -->
    <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-xs">
                <thead>
                    <tr class="bg-base-200/50 text-neutral uppercase font-black tracking-wider text-[10px]">
                        <th>POS Product ID</th>
                        <th>Nama Produk POS</th>
                        <th>Jumlah Bahan Baku Dasar</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipes as $recipe)
                        <tr class="hover:bg-base-200/40 transition-colors">
                            <td>
                                <span class="font-mono text-neutral bg-base-200 px-2.5 py-1 rounded-lg border border-base-300 font-extrabold">
                                    {{ $recipe->pos_product_id }}
                                </span>
                            </td>
                            <td>
                                <div class="font-extrabold text-neutral text-sm">{{ $recipe->pos_product_name }}</div>
                            </td>
                            <td>
                                <span class="badge badge-info text-white font-extrabold px-2.5 py-1 text-[11px]">
                                    {{ $recipe->total_ingredients }} Bahan Baku
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('recipes.show', $recipe->pos_product_id) }}" class="btn btn-sm btn-primary text-white rounded-xl font-bold gap-1.5 shadow-xs">
                                    <i data-lucide="settings-2" class="w-4 h-4"></i> Detail & Modifier
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-12 text-gray-400">
                                <i data-lucide="utensils-crossed" class="w-12 h-12 mx-auto mb-2 opacity-40"></i>
                                <div class="font-bold text-sm text-neutral">Belum Ada Resep BOM Terdaftar</div>
                                <div class="text-xs text-gray-500 mt-1">Gunakan menu Produk Unmapped untuk menambahkan resep dari transaksi POS.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recipes->hasPages())
            <div class="p-4 border-t border-base-300">
                {{ $recipes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
