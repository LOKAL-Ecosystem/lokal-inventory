<!DOCTYPE html>
<html lang="id" data-theme="lokal">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lokal Stock') - Lokal Stock</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 text-base-content min-h-screen antialiased selection:bg-primary selection:text-white">
    @php
        $authUser = auth()->user();
        $lowStockCount = \App\Models\Ingredient::lowStock()->count();
        $lowStockList = \App\Models\Ingredient::lowStock()->orderByRaw('current_stock - min_stock')->limit(5)->get();
        $stuckEvents = \App\Models\WebhookInbox::whereNull('processed_at')->whereNotNull('last_error')->count();
    @endphp

    <div class="drawer lg:drawer-open">
        <input id="main-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
            <nav class="navbar w-full bg-base-100/90 backdrop-blur-md border-b border-base-300/80 px-4 sm:px-8 flex justify-between items-center sticky top-0 z-30">
                <div class="flex items-center gap-3">
                    <label for="main-drawer" aria-label="Buka menu" class="btn btn-square btn-ghost btn-sm lg:hidden text-neutral">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </label>
                    <span class="font-extrabold text-lg tracking-tight text-neutral flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-orange-500 text-white flex items-center justify-center font-extrabold">L</span>
                        <span>Stock</span>
                    </span>
                </div>

                <div class="flex items-center gap-4">
                    <div class="dropdown dropdown-end">
                        <button tabindex="0" role="button" aria-label="Bahan hampir habis" class="w-11 h-11 rounded-xl bg-base-200 hover:bg-primary/10 hover:text-primary flex items-center justify-center text-neutral transition-all relative border border-base-300">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            @if($lowStockCount > 0)
                                <span class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[10px] font-black text-white ring-2 ring-white">{{ $lowStockCount }}</span>
                            @endif
                        </button>

                        <div tabindex="0" class="dropdown-content z-[100] mt-3 w-80 sm:w-96 rounded-2xl border border-base-300 bg-base-100 p-4 shadow-2xl">
                            <h3 class="font-extrabold text-xs uppercase tracking-wider text-neutral mb-3">Bahan Hampir Habis ({{ $lowStockCount }})</h3>
                            @forelse($lowStockList as $item)
                                <a href="{{ route('ingredients.show', $item) }}" class="flex justify-between items-center p-3 rounded-xl hover:bg-base-200 transition-colors">
                                    <div>
                                        <div class="font-bold text-xs text-neutral">{{ $item->name }}</div>
                                        <div class="text-[11px] text-base-content/50">Minimum {{ $item->formatQty($item->min_stock) }}</div>
                                    </div>
                                    <span class="badge {{ $item->isNegative() ? 'badge-error' : 'badge-warning' }} text-xs font-bold">{{ $item->formatQty($item->current_stock) }}</span>
                                </a>
                            @empty
                                <p class="text-center text-xs text-base-content/50 py-6">Semua stok masih di atas batas minimum.</p>
                            @endforelse
                            @if($lowStockCount > 0)
                                <a href="{{ route('ingredients.index', ['low' => 1]) }}" class="btn btn-primary btn-sm w-full mt-3">Lihat semua</a>
                            @endif
                        </div>
                    </div>

                    <div class="dropdown dropdown-end">
                        <button tabindex="0" role="button" class="flex items-center gap-3 pl-3 border-l border-base-300/80 min-h-11">
                            <div class="bg-neutral text-neutral-content rounded-xl w-9 h-9 flex items-center justify-center font-black text-xs">
                                {{ strtoupper(substr($authUser->name, 0, 2)) }}
                            </div>
                            <div class="hidden sm:block text-left">
                                <div class="text-xs font-extrabold text-neutral leading-snug">{{ $authUser->name }}</div>
                                <div class="text-[10px] text-base-content/50 font-bold uppercase tracking-wider">{{ $authUser->role->label() }}</div>
                            </div>
                        </button>
                        <ul tabindex="0" class="dropdown-content menu z-[100] mt-3 w-48 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-2xl">
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left flex items-center gap-2 min-h-11 text-error font-bold">
                                        <i data-lucide="log-out" class="w-4 h-4"></i> Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <main class="p-4 sm:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">
                @if (session('success'))
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-bold flex items-center justify-between">
                        <span>{{ session('success') }}</span>
                        <button type="button" aria-label="Tutup" onclick="this.parentElement.remove()" class="p-1"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-bold flex items-center justify-between">
                        <span>{{ session('error') }}</span>
                        <button type="button" aria-label="Tutup" onclick="this.parentElement.remove()" class="p-1"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>
                @endif

                @if ($errors->any() && ! $errors->hasAny(['email']))
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs font-semibold">
                        <ul class="list-disc pl-4 space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>

            <footer class="border-t border-base-300 py-4 px-8 bg-base-100/50 text-xs text-base-content/50 flex flex-col sm:flex-row justify-between items-center gap-1">
                <div>&copy; {{ date('Y') }} <strong>Lokal</strong> — {{ $authUser->outlet?->name }}</div>
                <div>
                    @if($authUser->outlet?->last_catalog_sync_at)
                        Katalog POS disinkronkan {{ $authUser->outlet->last_catalog_sync_at->diffForHumans() }}
                    @else
                        Katalog POS belum disinkronkan
                    @endif
                </div>
            </footer>
        </div>

        <div class="drawer-side z-40 border-r border-base-300/80">
            <label for="main-drawer" aria-label="Tutup menu" class="drawer-overlay"></label>
            <div class="flex min-h-full flex-col w-64 bg-base-100 p-4">
                <div class="flex items-center gap-3 px-3 py-4 border-b border-base-300/80 mb-4">
                    <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center text-white font-extrabold">L</div>
                    <div>
                        <span class="font-extrabold text-base text-neutral block leading-tight">Lokal Stock</span>
                        <span class="text-[10px] text-base-content/50 font-semibold tracking-wider uppercase">{{ $authUser->outlet?->name }}</span>
                    </div>
                </div>

                <ul class="menu w-full space-y-1 text-sm font-medium p-0">
                    <x-nav-link :href="route('dashboard')" icon="layout-dashboard" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                    <x-nav-link :href="route('ingredients.index')" icon="package-search" :active="request()->routeIs('ingredients.*')">Bahan Baku</x-nav-link>
                    @can('manage-inventory')
                        <x-nav-link :href="route('recipes.index')" icon="chef-hat" :active="request()->routeIs('recipes.*')">Resep</x-nav-link>
                    @endcan

                    <li class="menu-title pt-4 text-[10px] font-extrabold uppercase tracking-widest">Operasional</li>
                    <x-nav-link :href="route('stock-receipts.index')" icon="arrow-down-left" :active="request()->routeIs('stock-receipts.*')">Stok Masuk</x-nav-link>
                    <x-nav-link :href="route('waste.index')" icon="trash-2" :active="request()->routeIs('waste.*')">Waste</x-nav-link>
                    <x-nav-link :href="route('opnames.index')" icon="clipboard-check" :active="request()->routeIs('opnames.*')">Stok Opname</x-nav-link>
                    @can('manage-inventory')
                        <x-nav-link :href="route('suppliers.index')" icon="truck" :active="request()->routeIs('suppliers.*')">Supplier</x-nav-link>
                    @endcan

                    @canany(['view-costs', 'manage-inventory'])
                        <li class="menu-title pt-4 text-[10px] font-extrabold uppercase tracking-widest">Analitik &amp; Sistem</li>
                    @endcanany
                    @can('view-costs')
                        <x-nav-link :href="route('reports.index')" icon="bar-chart-3" :active="request()->routeIs('reports.*')">Laporan</x-nav-link>
                    @endcan
                    @can('manage-inventory')
                        <x-nav-link :href="route('settings.edit')" icon="plug" :active="request()->routeIs('settings.*')">
                            Koneksi POS
                            @if($stuckEvents > 0)
                                <span class="badge badge-error badge-sm ml-auto text-white font-black">{{ $stuckEvents }}</span>
                            @endif
                        </x-nav-link>
                    @endcan
                </ul>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>

</html>
