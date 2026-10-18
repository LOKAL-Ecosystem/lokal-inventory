@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard" :subtitle="'Ringkasan stok ' . $outlet->name">
    <x-slot:actions>
        <a href="{{ route('stock-receipts.create') }}" class="btn btn-primary btn-sm min-h-11 gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Catat Stok Masuk</a>
        <a href="{{ route('waste.index') }}" class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="trash-2" class="w-4 h-4"></i> Waste</a>
    </x-slot:actions>
</x-page-header>

<section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="card-premium p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Hampir habis</div>
        <div class="text-3xl font-extrabold {{ $lowStockCount ? 'text-warning' : 'text-success' }} mt-1">{{ $lowStockCount }}</div>
    </div>
    <div class="card-premium p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Stok negatif</div>
        <div class="text-3xl font-extrabold {{ $negativeStock->count() ? 'text-error' : 'text-success' }} mt-1">{{ $negativeStock->count() }}</div>
    </div>
    <div class="card-premium p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Menu tanpa resep</div>
        <div class="text-3xl font-extrabold {{ $noRecipe->count() ? 'text-warning' : 'text-success' }} mt-1">{{ $noRecipe->count() }}</div>
    </div>
    @can('view-costs')
        <div class="card-premium p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Nilai stok</div>
            <div class="text-2xl font-extrabold text-neutral mt-1">Rp {{ number_format($stockValue, 0, ',', '.') }}</div>
        </div>
    @else
        <div class="card-premium p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/50">Menu habis otomatis</div>
            <div class="text-3xl font-extrabold {{ $soldOut->count() ? 'text-error' : 'text-success' }} mt-1">{{ $soldOut->count() }}</div>
        </div>
    @endcan
</section>

<section class="card-premium p-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold">
    <span class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full {{ $outlet->hasPosConnection() ? 'bg-success' : 'bg-warning' }}"></span>
        Koneksi Lokal POS: {{ $outlet->hasPosConnection() ? 'terhubung' : 'belum diatur' }}
    </span>
    <span>Sinkron katalog: {{ $outlet->last_catalog_sync_at?->diffForHumans() ?? 'belum pernah' }}</span>
    <span>Rekonsiliasi pesanan: {{ $outlet->last_reconciled_at?->diffForHumans() ?? 'belum pernah' }}</span>
    <span class="{{ $failedEvents ? 'text-error' : '' }}">Event webhook tertunda: {{ $pendingEvents }}{{ $failedEvents ? " ({$failedEvents} gagal)" : '' }}</span>
</section>

<div class="grid lg:grid-cols-2 gap-6">
    <section class="card-premium overflow-hidden">
        <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Bahan hampir habis</h2>
        <table class="table-premium">
            <tbody>
                @forelse($lowStock as $item)
                    <tr>
                        <td><a href="{{ route('ingredients.show', $item) }}" class="font-bold text-neutral hover:text-primary">{{ $item->name }}</a></td>
                        <td class="text-right text-xs text-base-content/60">min {{ $item->formatQty($item->min_stock) }}</td>
                        <td class="text-right"><span class="badge {{ $item->isNegative() ? 'badge-error' : 'badge-warning' }} font-bold">{{ $item->formatQty($item->current_stock) }}</span></td>
                    </tr>
                @empty
                    <tr><td class="text-center py-8 text-xs text-base-content/50">Semua stok aman.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="card-premium overflow-hidden">
        <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Menu tanpa resep</h2>
        <table class="table-premium">
            <tbody>
                @forelse($noRecipe->take(10) as $product)
                    <tr>
                        <td class="font-bold text-neutral">{{ $product->name }}</td>
                        <td class="text-right text-xs text-base-content/60">
                            @if($product->missing_recipe_hits) terjual {{ $product->missing_recipe_hits }}× tanpa potong stok @endif
                        </td>
                        @can('manage-inventory')
                            <td class="text-right"><a href="{{ route('recipes.show', $product->pos_product_id) }}" class="btn btn-ghost btn-xs">Atur resep</a></td>
                        @endcan
                    </tr>
                @empty
                    <tr><td class="text-center py-8 text-xs text-base-content/50">Semua menu sudah punya resep.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @if($negativeStock->isNotEmpty())
        <section class="card-premium overflow-hidden">
            <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200 text-error">Stok negatif — perlu opname</h2>
            <table class="table-premium">
                <tbody>
                    @foreach($negativeStock as $item)
                        <tr>
                            <td><a href="{{ route('ingredients.show', $item) }}" class="font-bold text-neutral hover:text-primary">{{ $item->name }}</a></td>
                            <td class="text-right"><span class="badge badge-error font-bold">{{ $item->formatQty($item->current_stock) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if($soldOut->isNotEmpty())
        <section class="card-premium overflow-hidden">
            <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Menu ditandai habis (stok tidak cukup)</h2>
            <table class="table-premium">
                <tbody>
                    @foreach($soldOut as $product)
                        <tr><td class="font-bold text-neutral">{{ $product->name }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
@endsection
