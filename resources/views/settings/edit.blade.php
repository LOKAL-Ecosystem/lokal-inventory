@extends('layouts.app')

@section('title', 'Koneksi POS')

@section('content')
<x-page-header title="Koneksi Lokal POS" subtitle="URL, API key, dan webhook secret dari Integrasi di Lokal POS. Rahasia disimpan terenkripsi.">
    <x-slot:actions>
        <form method="POST" action="{{ route('settings.test') }}">@csrf
            <button class="btn btn-outline btn-sm min-h-11 gap-2"><i data-lucide="plug-zap" class="w-4 h-4"></i> Tes koneksi</button>
        </form>
        <form method="POST" action="{{ route('settings.sync') }}">@csrf
            <button class="btn btn-primary btn-sm min-h-11 gap-2"><i data-lucide="refresh-cw" class="w-4 h-4"></i> Sinkron katalog</button>
        </form>
    </x-slot:actions>
</x-page-header>

<form method="POST" action="{{ route('settings.update') }}" class="bg-base-100 border border-base-300 rounded-2xl p-5 space-y-4">
    @csrf @method('PUT')

    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="Nama outlet" name="name">
            <input type="text" name="name" value="{{ old('name', $outlet->name) }}" required class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <x-field label="ID outlet di Lokal POS" name="pos_outlet_id" hint="Opsional; untuk pencocokan outlet.">
            <input type="number" name="pos_outlet_id" value="{{ old('pos_outlet_id', $outlet->pos_outlet_id) }}" min="1" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
    </div>

    <x-field label="URL Lokal POS" name="pos_base_url" hint="Alamat dasar aplikasi POS, mis. https://pos.contoh.com (tanpa /api/v1).">
        <input type="url" name="pos_base_url" value="{{ old('pos_base_url', $outlet->pos_base_url) }}" class="input w-full min-h-11 text-sm rounded-xl" />
    </x-field>

    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="API key (X-Api-Key)" name="pos_api_key" :hint="$maskedKey ? 'Tersimpan: ' . $maskedKey . '. Kosongkan bila tidak diubah.' : 'Scope: catalog:read, orders:read.'">
            <input type="password" name="pos_api_key" autocomplete="off" placeholder="lkl_…" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
        <x-field label="Webhook secret" name="webhook_secret" :hint="$maskedSecret ? 'Tersimpan: ' . $maskedSecret . '. Kosongkan bila tidak diubah.' : 'whsec_… dari Integrasi → Webhook keluar.'">
            <input type="password" name="webhook_secret" autocomplete="off" placeholder="whsec_…" class="input w-full min-h-11 text-sm rounded-xl" />
        </x-field>
    </div>

    <div class="rounded-xl bg-base-200 p-4 text-xs space-y-1">
        <div class="font-extrabold">URL penerima webhook (isi di Lokal POS)</div>
        <code class="font-mono text-xs font-bold bg-base-200 border border-base-300 px-2 py-0.5 rounded-md break-all">{{ $webhookUrl }}</code>
        <p class="text-base-content/60">Langganan event: <strong>order.confirmed</strong>, <strong>order.voided</strong> (opsional: catalog.updated).</p>
    </div>

    <div class="flex justify-end"><button class="btn btn-primary min-h-11 px-8">Simpan</button></div>
</form>

<section class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden">
    <h2 class="px-5 py-4 font-extrabold text-sm border-b border-base-200">Webhook terbaru</h2>
    <div class="overflow-x-auto">
        <table class="table">
            <thead class="bg-base-200/20 text-[10px] uppercase tracking-wider font-extrabold text-base-content/50"><tr><th>Diterima</th><th>Event</th><th>Kunci</th><th>Status</th><th></th></tr></thead>
            <tbody class="[&>tr:hover]:bg-base-200/30">
                @forelse($inbox as $event)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $event->received_at->format('d M H:i:s') }}</td>
                        <td><span class="font-mono text-xs font-bold bg-base-200 border border-base-300 px-2 py-0.5 rounded-md">{{ $event->event_type }}</span></td>
                        <td class="text-[11px] font-mono break-all max-w-xs">{{ $event->event_id }}</td>
                        <td>
                            @if($event->processed_at) <span class="badge badge-success">Selesai</span>
                            @elseif($event->last_error)
                                <span class="badge badge-error">Gagal ({{ $event->attempts }}×)</span>
                                <div class="text-[11px] text-error mt-1 max-w-xs">{{ $event->last_error }}</div>
                            @else <span class="badge badge-warning">Menunggu</span> @endif
                        </td>
                        <td class="text-right">
                            @unless($event->processed_at)
                                <form method="POST" action="{{ route('settings.inbox.retry', $event) }}">@csrf
                                    <button class="btn btn-ghost btn-sm min-h-11">Proses ulang</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-10 text-xs text-base-content/50">Belum ada webhook masuk.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
