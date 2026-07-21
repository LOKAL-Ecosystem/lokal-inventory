<!DOCTYPE html>
<html lang="id" data-theme="lokal">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Lokal Inventory') - Manajemen Stok</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 text-base-content min-h-screen antialiased selection:bg-primary selection:text-white">
    @php
        $lowStockCount = \App\Models\Item::whereRaw('quantity_on_hand <= minimum_stock')->where('is_active', true)->count();
        $lowStockList = \App\Models\Item::with('unit')->whereRaw('quantity_on_hand <= minimum_stock')->where('is_active', true)->take(5)->get();
    @endphp

    <div class="drawer lg:drawer-open">
        <input id="my-drawer-4" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
            <!-- Navbar -->
            <nav class="navbar w-full bg-base-100/90 backdrop-blur-md border-b border-base-300/80 px-4 sm:px-8 flex justify-between items-center sticky top-0 z-30 transition-all">
                <div class="flex items-center gap-3">
                    <label for="my-drawer-4" aria-label="open sidebar" class="btn btn-square btn-ghost btn-sm lg:hidden text-neutral">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-lg tracking-tight text-neutral flex items-center gap-2.5">
                            <span class="w-8.5 h-8.5 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold shadow-xs border border-primary/20">
                                <i data-lucide="package" class="w-5 h-5"></i>
                            </span>
                            <span class="hidden sm:inline">Lokal Inventory</span>
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Low Stock Notification Dropdown -->
                    <div class="dropdown dropdown-end">
                        <button tabindex="0" role="button" aria-label="Notifikasi Stok" class="w-9 h-9 rounded-xl bg-slate-100/80 hover:bg-primary/10 hover:text-primary flex items-center justify-center text-neutral transition-all relative border border-slate-200/80">
                            <i data-lucide="bell" class="w-4.5 h-4.5"></i>
                            @if($lowStockCount > 0)
                                <span class="absolute -top-1 -right-1 flex h-4.5 min-w-[1.125rem] items-center justify-center rounded-full bg-error px-1 text-[10px] font-black text-white shadow-xs ring-2 ring-white animate-pulse">
                                    {{ $lowStockCount }}
                                </span>
                            @endif
                        </button>
                        
                        <div tabindex="0" class="dropdown-content z-[100] mt-3 w-84 sm:w-96 rounded-2xl border border-gray-200 bg-white/95 backdrop-blur-md p-4 shadow-2xl animate-fadeIn">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-warning/15 text-warning flex items-center justify-center font-bold">
                                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-extrabold text-xs uppercase tracking-wider text-neutral">Notifikasi Low Stock</h3>
                                        <p class="text-[10px] text-gray-400 font-medium">{{ $lowStockCount }} item di bawah batas minimum</p>
                                    </div>
                                </div>
                                <span class="badge badge-error text-[10px] font-extrabold">Kritis</span>
                            </div>

                            @if($lowStockCount > 0)
                                <div class="space-y-2 py-3 max-h-72 overflow-y-auto pr-1">
                                    @foreach($lowStockList as $item)
                                        <div class="flex justify-between items-center p-3 rounded-xl bg-red-50/50 border border-red-100 hover:bg-red-50 transition-colors">
                                            <div class="space-y-0.5">
                                                <div class="font-extrabold text-xs text-neutral tracking-tight">{{ $item->name }}</div>
                                                <div class="text-[11px] text-gray-400 font-mono">SKU: {{ $item->sku }}</div>
                                                <div class="text-[10px] text-gray-500 font-medium">Batas Min: {{ number_format($item->minimum_stock, 0) }} {{ $item->unit?->symbol }}</div>
                                            </div>
                                            <div class="text-right">
                                                <span class="badge badge-error text-white font-black text-xs px-2.5 py-1 mb-1 block">
                                                    {{ number_format($item->quantity_on_hand, 0) }} {{ $item->unit?->symbol }}
                                                </span>
                                                <a href="{{ route('stock-in.create') }}" class="text-[10px] text-primary font-bold hover:underline flex items-center justify-end gap-1">
                                                    Restock <i data-lucide="arrow-right" class="w-3 h-3"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <a href="{{ route('items.index', ['low_stock' => 1]) }}" class="btn btn-sm btn-primary text-white w-full rounded-xl font-bold py-2 h-auto text-xs shadow-sm flex items-center justify-center gap-1.5">
                                    <span>Kelola Semua Item Kritis</span>
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            @else
                                <div class="text-center py-8">
                                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2 border border-emerald-200">
                                        <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                                    </div>
                                    <div class="text-xs font-bold text-neutral">Stok Terkendali</div>
                                    <p class="text-[11px] text-gray-400 mt-0.5 font-medium">Semua stok bahan baku & produk dalam posisi aman.</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="flex items-center gap-3 pl-3 border-l border-base-300/80">
                        <div class="avatar placeholder">
                            <div class="bg-neutral text-neutral-content rounded-xl w-9 h-9 ring-2 ring-primary/20 flex items-center justify-center font-black text-xs shadow-xs">
                                {{ strtoupper(substr(auth()->user()->name ?? 'US', 0, 2)) }}
                            </div>
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-xs font-extrabold text-neutral leading-snug">{{ auth()->user()->name ?? 'User Staff' }}</div>
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                {{ auth()->user()->role ?? 'Staff' }}
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Header & Content -->
            <main class="p-4 sm:p-8 space-y-6 flex-1 max-w-7xl w-full mx-auto">
                @if (session('success'))
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 shadow-sm text-xs font-bold flex items-center justify-between animate-fadeIn">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="font-extrabold text-xs text-emerald-950">Transaksi Berhasil</div>
                                <div class="text-emerald-800 font-medium mt-0.5">{{ session('success') }}</div>
                            </div>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 p-1">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 shadow-sm text-xs font-bold flex items-center justify-between animate-fadeIn">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-lg bg-rose-600 text-white flex items-center justify-center shadow-xs">
                                <i data-lucide="alert-circle" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="font-extrabold text-xs text-rose-950">Terjadi Kesalahan</div>
                                <div class="text-rose-800 font-medium mt-0.5">{{ session('error') }}</div>
                            </div>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-rose-700 hover:text-rose-950 p-1">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- Sleek Footer -->
            <footer class="footer border-t border-base-300 py-4 px-8 bg-base-100/50 text-xs text-gray-400 flex flex-col sm:flex-row justify-between items-center">
                <div>&copy; 2026 <strong>Lokal Ecosystem</strong> — Sistem Manajemen Stok & Inventori</div>
                <div class="flex items-center gap-4 text-gray-400 font-medium">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-success animate-ping"></span> Synchronized with POS</span>
                    <span>v1.0.0</span>
                </div>
            </footer>
        </div>

        <!-- Sidebar Navigation -->
        <div class="drawer-side z-40 border-r border-base-300/80">
            <label for="my-drawer-4" aria-label="close sidebar" class="drawer-overlay"></label>
            <div class="flex min-h-full flex-col w-64 bg-base-100 text-base-content p-4 justify-between">
                <div>
                    <!-- Sidebar Header -->
                    <div class="flex items-center gap-3 px-3 py-4 border-b border-base-300/80 mb-4">
                        <div class="w-9.5 h-9.5 rounded-xl bg-neutral flex items-center justify-center text-white font-bold shadow-sm">
                            <i data-lucide="boxes" class="w-5 h-5 text-primary"></i>
                        </div>
                        <div>
                            <span class="font-extrabold text-base text-neutral block leading-tight tracking-tight">Lokal Admin</span>
                            <span class="text-[10px] text-gray-400 font-semibold tracking-wider uppercase">Inventory Core</span>
                        </div>
                    </div>

                    <!-- Navigation Menu -->
                    <ul class="menu w-full space-y-1 text-sm font-medium p-0">
                        <li>
                            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="layout-dashboard" class="w-4.5 h-4.5"></i>
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('items.index') }}" class="{{ request()->routeIs('items.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="package-search" class="w-4.5 h-4.5"></i>
                                Master Stok Item
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="truck" class="w-4.5 h-4.5"></i>
                                Manajemen Supplier
                            </a>
                        </li>
                        
                        <div class="pt-4 pb-1.5 px-3 text-[10px] font-extrabold uppercase tracking-widest text-gray-400">Operasional Stok</div>
                        
                        <li>
                            <a href="{{ route('stock-in.index') }}" class="{{ request()->routeIs('stock-in.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="arrow-down-left" class="w-4.5 h-4.5 text-success"></i>
                                Stock In / Restock
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('adjustments.index') }}" class="{{ request()->routeIs('adjustments.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="sliders" class="w-4.5 h-4.5 text-warning"></i>
                                Stock Adjustment
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('movements.index') }}" class="{{ request()->routeIs('movements.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="history" class="w-4.5 h-4.5"></i>
                                Kartu Stok / Mutasi
                            </a>
                        </li>

                        <div class="pt-4 pb-1.5 px-3 text-[10px] font-extrabold uppercase tracking-widest text-gray-400">Analitik & POS</div>

                        <li>
                            <a href="{{ route('reports.index') }}" class="{{ request()->routeIs('reports.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="bar-chart-3" class="w-4.5 h-4.5"></i>
                                Laporan Inventori
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('pos-integration.index') }}" class="{{ request()->routeIs('pos-integration.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="refresh-cw" class="w-4.5 h-4.5 text-primary"></i>
                                Integrasi Lokal-POS
                            </a>
                        </li>

                        <div class="pt-4 pb-1.5 px-3 text-[10px] font-extrabold uppercase tracking-widest text-gray-400">Webhook & Resep BOM</div>

                        <li>
                            <a href="{{ route('recipes.index') }}" class="{{ request()->routeIs('recipes.*') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="chef-hat" class="w-4.5 h-4.5 text-primary"></i>
                                Resep / BOM
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('webhooks.index') }}" class="{{ request()->routeIs('webhooks.index') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="activity" class="w-4.5 h-4.5 text-info"></i>
                                Riwayat Webhook
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('webhooks.unmapped') }}" class="{{ request()->routeIs('webhooks.unmapped') ? 'bg-primary/10 text-primary font-extrabold border-l-4 border-primary rounded-l-none' : 'hover:bg-base-200 text-gray-600 hover:text-neutral' }} flex items-center gap-3 px-3 py-2.5 transition-all">
                                <i data-lucide="file-question" class="w-4.5 h-4.5 text-warning"></i>
                                Produk Unmapped (BOM)
                                @php
                                    $unmappedCount = \App\Models\UnmappedProduct::count();
                                @endphp
                                @if($unmappedCount > 0)
                                    <span class="badge badge-warning badge-sm ml-auto font-black text-[10px] text-white">{{ $unmappedCount }}</span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Sidebar Footer Status -->
                <div class="p-3.5 bg-base-200/70 rounded-2xl border border-base-300/60 mt-4 space-y-1">
                    <div class="flex items-center justify-between text-xs font-extrabold text-neutral">
                        <span>Status Sistem</span>
                        <span class="badge badge-success text-white text-[10px] px-2 py-0.5 font-bold">ONLINE</span>
                    </div>
                    <p class="text-[11px] text-gray-500 font-medium">POS Webhook & REST API v1 aktif</p>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>

</html>
