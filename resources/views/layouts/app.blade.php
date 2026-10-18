@php
    $user = auth()->user();
    $outletName = $user->outlet?->name ?? 'Lokal Stock';
    $lowStockCount = \App\Models\Ingredient::lowStock()->count();
    $lowStockList = \App\Models\Ingredient::lowStock()->orderByRaw('current_stock - min_stock')->limit(5)->get();
    $stuckEvents = \App\Models\WebhookInbox::whereNull('processed_at')->whereNotNull('last_error')->count();
    $notifCount = $lowStockCount + ($user->can('manage-inventory') ? $stuckEvents : 0);
@endphp
<!DOCTYPE html>
<html lang="id" data-theme="lokal">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <title>@yield('title') - {{ $outletName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-base-200 min-h-screen" data-user-name="{{ $user->name }}">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 btn btn-primary btn-sm">
        Lewati ke konten
    </a>

    {{-- Flash messages are read by resources/js/app.js --}}
    <div id="flash-data" hidden
         data-success="{{ session('success') }}"
         data-error="{{ session('error') }}"></div>

    <div class="drawer lg:drawer-open">
        <input id="my-drawer-4" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex flex-col min-h-screen">
            <nav class="navbar relative z-40 w-full bg-base-100 border-b border-base-300 px-4 justify-between">
                <div class="flex items-center gap-2">
                    <label for="my-drawer-4" aria-label="Buka atau tutup menu samping" class="btn btn-square btn-ghost">
                        <i data-lucide="menu" class="w-5 h-5" aria-hidden="true"></i>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <span id="connection-badge" class="hidden badge badge-error gap-1.5 py-3 font-bold" role="status">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5" aria-hidden="true"></i>Tidak ada koneksi
                    </span>
                    <div id="notif" class="dropdown dropdown-end">
                        <button type="button" tabindex="0" class="btn btn-ghost btn-sm btn-square rounded-lg relative" aria-label="Notifikasi stok">
                            <i data-lucide="bell" class="w-5 h-5" aria-hidden="true"></i>
                            @if($notifCount > 0)
                                <span class="absolute -top-1 -right-1 min-w-4 h-4 px-1 rounded-full bg-error text-error-content text-[10px] font-bold leading-4 text-center" aria-live="polite">{{ $notifCount }}</span>
                            @endif
                        </button>
                        <div tabindex="0" class="dropdown-content z-50 mt-2 w-80 max-w-[90vw] rounded-2xl bg-base-100 border border-base-300 shadow-lg p-3">
                            @if($lowStockCount > 0)
                                <p class="text-xs font-bold uppercase tracking-wider text-base-content/60 px-2 pb-2">Bahan hampir habis ({{ $lowStockCount }})</p>
                                <ul class="divide-y divide-base-200">
                                    @foreach($lowStockList as $item)
                                        <li>
                                            <a href="{{ route('ingredients.show', $item) }}" class="flex justify-between items-center gap-3 px-2 py-2.5 min-h-11 rounded-lg hover:bg-base-200">
                                                <span>
                                                    <span class="block text-sm font-bold">{{ $item->name }}</span>
                                                    <span class="block text-[11px] text-base-content/50">Minimum {{ $item->formatQty($item->min_stock) }}</span>
                                                </span>
                                                <span class="badge {{ $item->isNegative() ? 'badge-error' : 'badge-warning' }} font-bold tabular-nums">{{ $item->formatQty($item->current_stock) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('ingredients.index', ['low' => 1]) }}" class="btn btn-primary btn-sm rounded-lg w-full mt-3">Lihat semua</a>
                            @endif
                            @can('manage-inventory')
                                @if($stuckEvents > 0)
                                    <p class="text-xs font-bold uppercase tracking-wider text-base-content/60 px-2 pb-2 {{ $lowStockCount > 0 ? 'pt-4' : '' }}">Webhook gagal diproses</p>
                                    <a href="{{ route('settings.edit') }}" class="flex justify-between items-center gap-3 px-2 py-2.5 min-h-11 rounded-lg hover:bg-base-200">
                                        <span class="text-sm font-bold">Periksa koneksi POS</span>
                                        <span class="badge badge-error font-bold tabular-nums">{{ $stuckEvents }}</span>
                                    </a>
                                @endif
                            @endcan
                            @if($notifCount === 0)
                                <p class="text-sm text-center text-base-content/60 py-4">Tidak ada notifikasi</p>
                            @endif
                        </div>
                    </div>
                    <div class="hidden sm:flex flex-col items-end text-xs">
                        <span class="font-bold text-base-content">{{ $user->name }}</span>
                        <span class="text-[10px] text-base-content/60 uppercase tracking-widest font-extrabold">{{ $user->role->label() }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-error btn-sm rounded-lg gap-2">
                            <i data-lucide="log-out" class="w-4 h-4" aria-hidden="true"></i>
                            Keluar
                        </button>
                    </form>
                </div>
            </nav>

            <main id="main-content" class="flex-1 p-4 md:p-6 overflow-y-auto flex flex-col">
                <div class="w-full space-y-6">
                    @if ($errors->any() && ! $errors->hasAny(['email']))
                        <div class="p-4 rounded-xl bg-error/10 border border-error/20 text-error text-sm font-semibold" role="alert">
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>

        <div class="drawer-side is-drawer-close:overflow-visible z-20">
            <label for="my-drawer-4" aria-label="Tutup menu samping" class="drawer-overlay"></label>

            <div class="flex min-h-full flex-col items-start bg-base-100 border-r border-base-300 is-drawer-close:w-14 is-drawer-open:w-64 transition-all duration-300">
                <div class="flex items-center gap-3 px-4 py-4 w-full is-drawer-close:justify-center border-b border-base-300 h-[65px] shrink-0">
                    <img src="{{ asset('logo.svg') }}" alt="" class="w-8 h-8 shrink-0" aria-hidden="true">
                    <span class="font-black text-lg tracking-wider text-base-content is-drawer-close:hidden">STOK</span>
                </div>

                <ul class="menu w-full grow px-2 py-4 space-y-1 font-semibold text-sm flex-nowrap is-drawer-open:overflow-y-auto" aria-label="Navigasi utama">
                    <li class="menu-title is-drawer-close:hidden">Stok</li>
                    <x-nav-link route="dashboard" icon="layout-dashboard" label="Dashboard" />
                    <x-nav-link route="ingredients.index" match="ingredients.*" icon="package-search" label="Bahan Baku" />
                    <x-nav-link route="stock-receipts.index" match="stock-receipts.*" icon="arrow-down-left" label="Stok Masuk" />
                    <x-nav-link route="waste.index" match="waste.*" icon="trash-2" label="Waste" />
                    <x-nav-link route="opnames.index" match="opnames.*" icon="clipboard-check" label="Stok Opname" />

                    @can('manage-inventory')
                        <li class="menu-title is-drawer-close:hidden">Katalog</li>
                        <x-nav-link route="recipes.index" match="recipes.*" icon="chef-hat" label="Resep" />
                        <x-nav-link route="suppliers.index" match="suppliers.*" icon="truck" label="Supplier" />
                    @endcan

                    @canany(['view-costs', 'manage-inventory'])
                        <li class="menu-title is-drawer-close:hidden">Analitik &amp; Pengaturan</li>
                    @endcanany
                    @can('view-costs')
                        <x-nav-link route="reports.index" match="reports.*" icon="bar-chart-3" label="Laporan" />
                    @endcan
                    @can('manage-inventory')
                        <x-nav-link route="settings.edit" match="settings.*" icon="plug" label="Koneksi POS" />
                    @endcan
                </ul>

                <div class="p-4 border-t border-base-300 w-full flex flex-col gap-3 is-drawer-close:items-center shrink-0">
                    <div class="flex items-center gap-3 w-full is-drawer-close:justify-center">
                        <div class="avatar placeholder shrink-0" aria-hidden="true">
                            <div class="bg-primary text-primary-content rounded-full w-8 flex items-center justify-center">
                                <i data-lucide="user" class="w-3.5 h-3.5"></i>
                            </div>
                        </div>
                        <div class="text-xs is-drawer-close:hidden">
                            <div class="font-bold text-base-content leading-none">{{ $outletName }}</div>
                            <div class="text-[10px] text-base-content/60 mt-1">
                                @if($user->outlet?->last_catalog_sync_at)
                                    Katalog disinkronkan {{ $user->outlet->last_catalog_sync_at->diffForHumans() }}
                                @else
                                    Katalog belum disinkronkan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
