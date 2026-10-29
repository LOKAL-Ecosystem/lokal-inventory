@extends('layouts.app')

@section('title', 'Koneksi POS')

@php
    $connected = $outlet->hasPosConnection();
@endphp

@section('content')
<x-page-header title="Koneksi Lokal POS" subtitle="Tersambung otomatis dari Lokal POS. Tidak ada yang perlu diisi di sini.">
    <x-slot:actions>
        @if($connected)
            <form method="POST" action="{{ route('settings.test') }}">@csrf
                <button class="btn btn-outline rounded-lg btn-sm min-h-11 gap-2"><i data-lucide="plug-zap" class="w-4 h-4" aria-hidden="true"></i> Tes koneksi</button>
            </form>
            <form method="POST" action="{{ route('settings.sync') }}">@csrf
                <button class="btn btn-primary rounded-lg btn-sm min-h-11 gap-2 font-bold"><i data-lucide="refresh-cw" class="w-4 h-4" aria-hidden="true"></i> Sinkron katalog sekarang</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<section class="bg-base-100 border border-base-300 rounded-2xl p-5 space-y-4" aria-label="Status koneksi">
    <div class="flex flex-wrap items-center gap-3">
        <span class="w-3 h-3 rounded-full {{ $connected ? 'bg-success' : 'bg-warning' }}" aria-hidden="true"></span>
        <h2 class="font-extrabold text-lg">
            @if($connected)
                Terhubung ke Lokal POS
            @elseif($linkEnabled)
                Menunggu penautan dari Lokal POS
            @else
                Penautan otomatis belum diaktifkan
            @endif
        </h2>
        <x-status-pill :tone="$connected ? 'success' : 'warning'">{{ $connected ? 'Otomatis' : 'Belum tersambung' }}</x-status-pill>
    </div>

    @unless($connected)
        <p class="text-sm text-base-content/60">
            @if($linkEnabled)
                Outlet ini tersambung sendiri setelah dibuat di Lokal POS. Bila belum muncul dalam beberapa menit, Lokal POS akan mencoba lagi otomatis.
            @else
                Operator perlu mengisi LOKAL_LINK_SECRET yang sama di Lokal POS dan Lokal Stock.
            @endif
        </p>
    @endunless

    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        <div>
            <dt class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Tersambung sejak</dt>
            <dd class="font-bold mt-1">{{ $outlet->linked_at?->locale('id')->translatedFormat('d M Y H:i') ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Sinkron katalog</dt>
            <dd class="font-bold mt-1">{{ $outlet->last_catalog_sync_at?->locale('id')->diffForHumans() ?? 'Belum pernah' }}</dd>
        </div>
        <div>
            <dt class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Rekonsiliasi pesanan</dt>
            <dd class="font-bold mt-1">{{ $outlet->last_reconciled_at?->locale('id')->diffForHumans() ?? 'Belum pernah' }}</dd>
        </div>
        <div>
            <dt class="text-[10px] font-extrabold uppercase tracking-widest text-base-content/60">Event gagal</dt>
            <dd class="font-bold mt-1 {{ $failedEvents ? 'text-error' : '' }}">{{ $failedEvents }}</dd>
        </div>
    </dl>
</section>

@if(session('scope_results'))
    <section class="bg-base-100 border border-base-300 rounded-2xl p-5 space-y-3">
        <h2 class="font-extrabold text-lg">Hasil tes koneksi</h2>
        <ul class="divide-y divide-base-200">
            @foreach($scopes as $scope => $purpose)
                @php($result = session('scope_results')[$scope] ?? null)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div>
                        <span class="text-sm font-bold">{{ $purpose }}</span>
                        <span class="text-[11px] font-mono text-base-content/50 ml-2">{{ $scope }}</span>
                    </div>
                    @if($result)
                        <x-status-pill :tone="$result['ok'] ? 'success' : 'error'" role="status">{{ $result['ok'] ? 'OK' : 'Gagal' }}</x-status-pill>
                        @unless($result['ok'])<span class="basis-full text-[11px] text-error">{{ $result['message'] }}</span>@endunless
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif

<section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <h2 class="px-5 py-4 font-extrabold text-lg border-b border-base-200">Event dari Lokal POS</h2>
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50"><tr><th scope="col">Diterima</th><th scope="col">Event</th><th scope="col">Status</th><th scope="col"></th></tr></thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($inbox as $event)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $event->received_at->format('d M H:i:s') }}</td>
                        <td><span class="font-mono text-xs font-bold bg-base-200 border border-base-300 px-2 py-0.5 rounded-md">{{ $event->event_type }}</span></td>
                        <td>
                            @if($event->processed_at) <x-status-pill tone="success">Selesai</x-status-pill>
                            @elseif($event->last_error)
                                <x-status-pill tone="error">Gagal ({{ $event->attempts }}×)</x-status-pill>
                                <div class="text-[11px] text-error mt-1 max-w-xs">{{ $event->last_error }}</div>
                            @else <x-status-pill tone="warning">Menunggu</x-status-pill> @endif
                        </td>
                        <td class="text-right">
                            @unless($event->processed_at)
                                <form method="POST" action="{{ route('settings.inbox.retry', $event) }}">@csrf
                                    <button class="btn btn-ghost btn-sm min-h-11">Coba lagi</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-sm text-base-content/60 py-10" role="status">Belum ada event dari Lokal POS.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
