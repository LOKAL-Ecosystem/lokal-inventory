@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $showCosts = $analytics['show_costs'];
    $rupiah = fn ($value) => 'Rp ' . number_format($value, 0, ',', '.');
    $number = fn ($value) => number_format($value, 0, ',', '.');
    $change = fn ($now, $before) => $before > 0 ? ($now - $before) / $before * 100 : null;
    $today = $analytics['today'];
    $yesterday = $analytics['yesterday'];
    $totals = $analytics['totals'];

    // good: arah perubahan yang baik (waste naik = buruk).
    $tiles = [['label' => 'Pesanan diproses', 'icon' => 'receipt', 'key' => 'orders', 'format' => $number, 'good' => 'up', 'hint' => 'Pesanan lunas dari POS yang memotong stok']];
    if ($showCosts) {
        $tiles[] = ['label' => 'Biaya bahan terpakai', 'icon' => 'coins', 'key' => 'usage_cost', 'format' => $rupiah, 'good' => 'up', 'hint' => 'Pada biaya rata-rata saat ini'];
        $tiles[] = ['label' => 'Nilai waste', 'icon' => 'trash-2', 'key' => 'waste_cost', 'format' => $rupiah, 'good' => 'down', 'hint' => $today['waste_count'] . ' catatan waste'];
        $tiles[] = ['label' => 'Stok masuk', 'icon' => 'truck', 'key' => 'purchase_cost', 'format' => $rupiah, 'good' => null, 'hint' => $today['purchase_count'] . ' baris belanja'];
    } else {
        $tiles[] = ['label' => 'Catatan waste', 'icon' => 'trash-2', 'key' => 'waste_count', 'format' => $number, 'good' => 'down', 'hint' => 'Bahan terbuang yang dicatat'];
        $tiles[] = ['label' => 'Baris stok masuk', 'icon' => 'truck', 'key' => 'purchase_count', 'format' => $number, 'good' => null, 'hint' => 'Bahan yang diterima hari ini'];
    }

    $movementTones = ['purchase' => 'success', 'waste' => 'warning', 'adjustment' => 'info', 'void_return' => 'neutral', 'sale' => 'neutral'];
@endphp

@section('content')
<div class="space-y-6 viz-root">
    <x-page-header title="Dashboard" :subtitle="'Ringkasan stok ' . $outlet->name">
        <x-slot:actions>
            <a href="{{ route('stock-receipts.create') }}" class="btn btn-primary rounded-lg btn-sm min-h-11 gap-2 font-bold"><i data-lucide="plus" class="w-4 h-4" aria-hidden="true"></i> Catat Stok Masuk</a>
            <a href="{{ route('waste.index') }}" class="btn btn-outline rounded-lg btn-sm min-h-11 gap-2"><i data-lucide="trash-2" class="w-4 h-4" aria-hidden="true"></i> Waste</a>
        </x-slot:actions>
    </x-page-header>

    @if($draftOpname)
        @php([$opname, $counted, $total] = $draftOpname)
        <a href="{{ route('opnames.show', $opname) }}" class="flex flex-wrap items-center gap-3 bg-primary/5 border border-primary/20 rounded-2xl px-4 py-3 min-h-11 hover:bg-primary/10 transition-colors">
            <i data-lucide="clipboard-check" class="w-5 h-5 text-primary shrink-0" aria-hidden="true"></i>
            <span class="text-sm">
                <strong class="font-extrabold">Opname belum selesai</strong>
                <span class="text-base-content/60">· {{ $opname->opname_date->locale('id')->translatedFormat('d M Y') }}{{ $opname->note ? ' · ' . $opname->note : '' }}</span>
            </span>
            <span class="ml-auto flex items-center gap-3">
                <progress class="progress progress-primary w-28" value="{{ $counted }}" max="{{ max(1, $total) }}" aria-label="Bahan sudah dihitung"></progress>
                <span class="text-xs font-bold tabular-nums">{{ $counted }}/{{ $total }} dihitung</span>
            </span>
        </a>
    @endif

    {{-- ============ Status stok saat ini ============ --}}
    <section class="grid grid-cols-2 {{ $showCosts ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-3" aria-label="Status stok saat ini">
        @foreach([
            ['Hampir habis', $lowStockCount, 'warning', route('ingredients.index', ['low' => 1])],
            ['Stok negatif', $negativeStock->count(), 'error', route('ingredients.index')],
            ['Menu habis otomatis', $soldOut->count(), 'error', auth()->user()->can('manage-inventory') ? route('recipes.index') : null],
            ['Menu tanpa resep', $noRecipe->count(), 'warning', auth()->user()->can('manage-inventory') ? route('recipes.index') : null],
        ] as [$label, $count, $tone, $href])
            <{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="bg-base-100 border border-base-300 rounded-2xl p-4 {{ $href ? 'hover:border-primary/40 transition-colors' : '' }}">
                <div class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">{{ $label }}</div>
                <div class="text-2xl font-black tabular-nums mt-1 {{ $count ? 'text-' . $tone : 'text-success' }}">{{ $count }}</div>
            </{{ $href ? 'a' : 'div' }}>
        @endforeach
        @if($showCosts)
            <div class="bg-base-100 border border-base-300 rounded-2xl p-4 col-span-2 lg:col-span-1">
                <div class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Nilai stok</div>
                <div class="text-2xl font-black tabular-nums mt-1 text-primary">{{ $rupiah($stockValue) }}</div>
            </div>
        @endif
    </section>

    {{-- ============ Hari ini ============ --}}
    <section aria-labelledby="today-heading" class="space-y-3">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <h2 id="today-heading" class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Hari ini</h2>
            <span class="text-xs text-base-content/50">Perubahan dibanding kemarin pada jam yang sama</span>
        </div>
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach($tiles as $tile)
                @php($delta = $change($today[$tile['key']], $yesterday[$tile['key']]))
                <div class="bg-base-100 border border-base-300 rounded-2xl p-4">
                    <div class="flex items-center justify-between text-base-content/60">
                        <span class="text-xs font-semibold">{{ $tile['label'] }}</span>
                        <i data-lucide="{{ $tile['icon'] }}" class="w-4 h-4" aria-hidden="true"></i>
                    </div>
                    <div class="mt-2 text-2xl font-black tabular-nums leading-tight">{{ ($tile['format'])($today[$tile['key']]) }}</div>
                    <div class="mt-1 text-xs flex flex-wrap items-center gap-1">
                        @if($delta === null)
                            <span class="text-base-content/50">{{ $tile['hint'] }}</span>
                        @else
                            @php($tone = $tile['good'] === null ? '' : (($delta >= 0) === ($tile['good'] === 'up') ? 'viz-up' : 'viz-down'))
                            <i data-lucide="{{ $delta >= 0 ? 'trending-up' : 'trending-down' }}" class="w-3.5 h-3.5 {{ $tone }}" aria-hidden="true"></i>
                            <span class="font-bold {{ $tone }}">{{ $delta >= 0 ? '+' : '−' }}{{ number_format(abs($delta), 1, ',', '.') }}%</span>
                            <span class="text-base-content/50">vs kemarin</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============ Periode analitik ============ --}}
    <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-base-300">
        <span class="text-sm font-bold">Periode analitik</span>
        <nav class="flex gap-2" aria-label="Pilih periode analitik">
            @foreach($ranges as $range)
                <a href="{{ route('dashboard', ['range' => $range]) }}" @if($analytics['days'] === $range) aria-current="true" @endif
                    @class(['btn btn-sm rounded-lg min-h-11 normal-case', 'btn-primary' => $analytics['days'] === $range, 'bg-base-100 border-base-300 hover:bg-base-200' => $analytics['days'] !== $range])>{{ $range }} hari</a>
            @endforeach
        </nav>
        <span class="text-xs text-base-content/50">
            {{ \Carbon\Carbon::parse($analytics['start'])->locale('id')->translatedFormat('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($analytics['end'])->locale('id')->translatedFormat('d M Y') }}
        </span>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <figure class="card bg-base-100 border border-base-300 rounded-2xl xl:col-span-2" data-chart="trend">
            <div class="card-body p-5 gap-4">
                <figcaption class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-lg">{{ $showCosts ? 'Biaya bahan terpakai' : 'Pesanan diproses' }}</h2>
                        <p class="text-xs text-base-content/60">Per hari, {{ $analytics['days'] }} hari terakhir</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" data-table-toggle aria-pressed="false">
                        <i data-lucide="table" class="w-3.5 h-3.5" aria-hidden="true"></i><span>Tabel</span>
                    </button>
                </figcaption>
                <dl class="flex flex-wrap gap-x-8 gap-y-2 text-xs">
                    @if($showCosts)
                        <div><dt class="text-base-content/60">Total terpakai</dt><dd class="text-2xl font-black tabular-nums">{{ $rupiah($totals['usage_cost']) }}</dd></div>
                    @endif
                    <div><dt class="text-base-content/60">Pesanan</dt><dd class="text-lg font-bold tabular-nums">{{ $number($totals['orders']) }}</dd></div>
                    @if($showCosts && $totals['orders'] > 0)
                        <div><dt class="text-base-content/60">Rata-rata bahan per pesanan</dt><dd class="text-lg font-bold tabular-nums">{{ $rupiah($totals['usage_cost'] / $totals['orders']) }}</dd></div>
                    @endif
                    <div><dt class="text-base-content/60">Catatan waste</dt><dd class="text-lg font-bold tabular-nums">{{ $number($totals['waste_count']) }}</dd></div>
                </dl>
                <div data-chart-body class="min-h-[240px]"></div>
                <div data-table-body class="hidden overflow-x-auto max-h-72"></div>
            </div>
        </figure>

        <figure class="card bg-base-100 border border-base-300 rounded-2xl" data-chart="flow">
            <div class="card-body p-5 gap-4">
                <figcaption class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-lg">Arus stok</h2>
                        <p class="text-xs text-base-content/60">{{ $showCosts ? 'Nilai' : 'Jumlah pergerakan' }} per jenis, {{ $analytics['days'] }} hari terakhir</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" data-table-toggle aria-pressed="false">
                        <i data-lucide="table" class="w-3.5 h-3.5" aria-hidden="true"></i><span>Tabel</span>
                    </button>
                </figcaption>
                <div data-chart-body></div>
                <div data-table-body class="hidden overflow-x-auto"></div>
            </div>
        </figure>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <figure class="card bg-base-100 border border-base-300 rounded-2xl" data-chart="cover">
            <div class="card-body p-5 gap-4">
                <figcaption class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-lg flex items-center gap-2"><i data-lucide="timer" class="w-4 h-4 text-primary" aria-hidden="true"></i> Perkiraan stok habis</h2>
                        <p class="text-xs text-base-content/60">Sisa hari dengan rata-rata pemakaian 7 hari terakhir</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" data-table-toggle aria-pressed="false">
                        <i data-lucide="table" class="w-3.5 h-3.5" aria-hidden="true"></i><span>Tabel</span>
                    </button>
                </figcaption>
                <div data-chart-body></div>
                <div data-table-body class="hidden overflow-x-auto"></div>
            </div>
        </figure>

        <figure class="card bg-base-100 border border-base-300 rounded-2xl" data-chart="waste">
            <div class="card-body p-5 gap-4">
                <figcaption class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-lg">Waste per alasan</h2>
                        <p class="text-xs text-base-content/60">{{ $showCosts ? 'Nilai bahan terbuang' : 'Jumlah catatan' }}, {{ $analytics['days'] }} hari terakhir</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" data-table-toggle aria-pressed="false">
                        <i data-lucide="table" class="w-3.5 h-3.5" aria-hidden="true"></i><span>Tabel</span>
                    </button>
                </figcaption>
                <div data-chart-body></div>
                <div data-table-body class="hidden overflow-x-auto"></div>
            </div>
        </figure>
    </div>

    {{-- ============ Margin & aktivitas ============ --}}
    <div class="grid grid-cols-1 {{ $showCosts ? 'lg:grid-cols-2' : '' }} gap-6">
        @if($showCosts)
            <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-base-200 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-extrabold text-lg">Margin terendah</h2>
                        <p class="text-xs text-base-content/60">HPP resep dasar dengan biaya rata-rata terbaru</p>
                    </div>
                    <a href="{{ route('reports.index', ['type' => 'hpp']) }}" class="btn btn-ghost btn-xs">Laporan HPP</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50">
                            <tr><th scope="col">Menu</th><th scope="col" class="text-right">Harga</th><th scope="col" class="text-right">HPP</th><th scope="col" class="text-right">Margin</th></tr>
                        </thead>
                        <tbody class="[&>tr:hover]:bg-base-200/30">
                            @forelse($lowestMargins as $row)
                                <tr>
                                    <td><a href="{{ route('recipes.show', $row['product']->pos_product_id) }}" class="font-bold hover:text-primary">{{ $row['product']->name }}</a></td>
                                    <td class="text-right tabular-nums">{{ $rupiah($row['product']->price) }}</td>
                                    <td class="text-right tabular-nums">{{ $rupiah($row['hpp']) }}</td>
                                    <td class="text-right">
                                        <x-status-pill :tone="$row['margin_pct'] < 50 ? 'error' : ($row['margin_pct'] < 65 ? 'warning' : 'success')" :upper="false" class="tabular-nums">{{ number_format($row['margin_pct'], 1, ',', '.') }}%</x-status-pill>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-sm text-base-content/60 py-10" role="status">Belum ada menu dengan resep.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-base-200">
                <h2 class="font-extrabold text-lg">Aktivitas terbaru</h2>
                <p class="text-xs text-base-content/60">Stok masuk, waste, opname, dan void (tanpa penjualan otomatis)</p>
            </div>
            <ul class="divide-y divide-base-200">
                @forelse($recentActivity as $movement)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <x-status-pill :tone="$movementTones[$movement->type->value] ?? 'neutral'">{{ $movement->type->label() }}</x-status-pill>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('ingredients.show', $movement->ingredient) }}" class="block text-sm font-bold truncate hover:text-primary">{{ $movement->ingredient->name }}</a>
                            <span class="block text-[11px] text-base-content/50 truncate">
                                {{ $movement->created_at->locale('id')->diffForHumans() }}{{ $movement->creator ? ' · ' . $movement->creator->name : '' }}{{ $movement->reason ? ' · ' . (\App\Enums\WasteReason::tryFrom($movement->reason)?->label() ?? $movement->reason) : '' }}
                            </span>
                        </div>
                        <span class="text-sm font-black tabular-nums whitespace-nowrap {{ (float) $movement->qty >= 0 ? 'text-success' : 'text-error' }}">
                            {{ (float) $movement->qty > 0 ? '+' : '' }}{{ $movement->ingredient->formatQty($movement->qty) }}
                        </span>
                    </li>
                @empty
                    <li class="text-center text-sm text-base-content/60 py-10" role="status">Belum ada aktivitas.</li>
                @endforelse
            </ul>
        </section>
    </div>

    {{-- ============ Perlu perhatian ============ --}}
    <div class="grid lg:grid-cols-2 gap-6">
        <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
            <h2 class="px-5 py-4 font-extrabold text-lg border-b border-base-200">Bahan hampir habis</h2>
            <table class="table">
                <tbody class="[&>tr:hover]:bg-base-200/30">
                    @forelse($lowStock as $item)
                        <tr>
                            <td><a href="{{ route('ingredients.show', $item) }}" class="font-bold hover:text-primary">{{ $item->name }}</a></td>
                            <td class="text-right text-xs text-base-content/60">min {{ $item->formatQty($item->min_stock) }}</td>
                            <td class="text-right"><x-status-pill :tone="$item->isNegative() ? 'error' : 'warning'" :upper="false" class="tabular-nums">{{ $item->formatQty($item->current_stock) }}</x-status-pill></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-sm text-base-content/60 py-10" role="status">Semua stok aman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
            <h2 class="px-5 py-4 font-extrabold text-lg border-b border-base-200">Menu tanpa resep</h2>
            <table class="table">
                <tbody class="[&>tr:hover]:bg-base-200/30">
                    @forelse($noRecipe->take(10) as $product)
                        <tr>
                            <td class="font-bold">{{ $product->name }}</td>
                            <td class="text-right text-xs text-base-content/60">
                                @if($product->missing_recipe_hits) terjual {{ $product->missing_recipe_hits }}× tanpa potong stok @endif
                            </td>
                            @can('manage-inventory')
                                <td class="text-right"><a href="{{ route('recipes.show', $product->pos_product_id) }}" class="btn btn-ghost btn-xs">Atur resep</a></td>
                            @endcan
                        </tr>
                    @empty
                        <tr><td class="text-center text-sm text-base-content/60 py-10" role="status">Semua menu sudah punya resep.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        @if($negativeStock->isNotEmpty())
            <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
                <h2 class="px-5 py-4 font-extrabold text-lg border-b border-base-200 text-error">Stok negatif — perlu opname</h2>
                <table class="table">
                    <tbody class="[&>tr:hover]:bg-base-200/30">
                        @foreach($negativeStock as $item)
                            <tr>
                                <td><a href="{{ route('ingredients.show', $item) }}" class="font-bold hover:text-primary">{{ $item->name }}</a></td>
                                <td class="text-right"><x-status-pill tone="error" :upper="false" class="tabular-nums">{{ $item->formatQty($item->current_stock) }}</x-status-pill></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if($soldOut->isNotEmpty())
            <section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
                <h2 class="px-5 py-4 font-extrabold text-lg border-b border-base-200">Menu ditandai habis (stok tidak cukup)</h2>
                <table class="table">
                    <tbody class="[&>tr:hover]:bg-base-200/30">
                        @foreach($soldOut as $product)
                            <tr>
                                <td class="font-bold">{{ $product->name }}</td>
                                <td class="text-right"><x-status-pill tone="error">Habis (stok)</x-status-pill></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>

    <section class="bg-base-100 border border-base-300 rounded-2xl p-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold" aria-label="Status integrasi">
        <span class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full {{ $outlet->hasPosConnection() ? 'bg-success' : 'bg-warning' }}"></span>
            Koneksi Lokal POS: {{ $outlet->hasPosConnection() ? 'terhubung' : 'belum diatur' }}
        </span>
        <span>Sinkron katalog: {{ $outlet->last_catalog_sync_at?->diffForHumans() ?? 'belum pernah' }}</span>
        <span>Rekonsiliasi pesanan: {{ $outlet->last_reconciled_at?->diffForHumans() ?? 'belum pernah' }}</span>
        <span class="{{ $failedEvents ? 'text-error' : '' }}">Event webhook tertunda: {{ $pendingEvents }}{{ $failedEvents ? " ({$failedEvents} gagal)" : '' }}</span>
    </section>
</div>

<div id="viz-tooltip" class="viz-tooltip hidden" role="status" aria-live="polite"></div>
<script type="application/json" id="dashboard-data">{!! json_encode($analytics, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endsection

@push('scripts')
    @vite('resources/js/pages/dashboard.js')
@endpush
