@extends('layouts.app')

@section('title', 'Riwayat Webhook POS')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-black text-neutral tracking-tight flex items-center gap-2.5">
                <i data-lucide="activity" class="w-7 h-7 text-info"></i>
                Riwayat Webhook POS
            </h1>
            <p class="text-xs text-gray-500 font-medium mt-1">
                Audit log event webhook yang diterima dari aplikasi POS beserta status pemrosesan idempotensi.
            </p>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center gap-2 bg-base-100 p-3 rounded-2xl border border-base-300 shadow-xs">
        <a href="{{ route('webhooks.index') }}" class="btn btn-sm {{ !$status ? 'btn-neutral' : 'btn-ghost text-gray-500' }} rounded-xl text-xs font-bold">
            Semua
        </a>
        <a href="{{ route('webhooks.index', ['status' => 'processed']) }}" class="btn btn-sm {{ $status === 'processed' ? 'btn-success text-white' : 'btn-ghost text-gray-500' }} rounded-xl text-xs font-bold">
            Processed
        </a>
        <a href="{{ route('webhooks.index', ['status' => 'received']) }}" class="btn btn-sm {{ $status === 'received' ? 'btn-warning text-white' : 'btn-ghost text-gray-500' }} rounded-xl text-xs font-bold">
            Received
        </a>
        <a href="{{ route('webhooks.index', ['status' => 'failed']) }}" class="btn btn-sm {{ $status === 'failed' ? 'btn-error text-white' : 'btn-ghost text-gray-500' }} rounded-xl text-xs font-bold">
            Failed
        </a>
    </div>

    <!-- Webhook Table -->
    <div class="bg-base-100 rounded-3xl border border-base-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full text-xs">
                <thead>
                    <tr class="bg-base-200/50 text-neutral uppercase font-black tracking-wider text-[10px]">
                        <th>ID / Waktu</th>
                        <th>Idempotency Key</th>
                        <th>Event Type</th>
                        <th>Status</th>
                        <th>Payload Summary</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($webhooks as $webhook)
                        <tr class="hover:bg-base-200/40 transition-colors">
                            <td>
                                <div class="font-bold text-neutral">#{{ $webhook->id }}</div>
                                <div class="text-[10px] text-gray-400 font-mono">{{ $webhook->created_at->format('d M Y H:i:s') }}</div>
                            </td>
                            <td>
                                <span class="font-mono text-neutral bg-base-200 px-2 py-0.5 rounded-lg border border-base-300 font-semibold">
                                    {{ $webhook->idempotency_key }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-ghost font-bold text-[11px]">
                                    {{ $webhook->event_type }}
                                </span>
                            </td>
                            <td>
                                @if($webhook->status === 'processed')
                                    <span class="badge badge-success text-white font-extrabold text-[10px] px-2 py-0.5">
                                        PROCESSED
                                    </span>
                                @elseif($webhook->status === 'received')
                                    <span class="badge badge-warning text-white font-extrabold text-[10px] px-2 py-0.5">
                                        RECEIVED
                                    </span>
                                @else
                                    <span class="badge badge-error text-white font-extrabold text-[10px] px-2 py-0.5" title="{{ $webhook->error_message }}">
                                        FAILED
                                    </span>
                                @endif
                            </td>
                            <td class="max-w-xs truncate text-gray-500 font-mono text-[11px]">
                                {{ json_encode($webhook->payload) }}
                            </td>
                            <td class="text-right">
                                <button onclick="showPayloadModal({{ $webhook->id }})" class="btn btn-xs btn-ghost text-primary font-bold">
                                    <i data-lucide="code" class="w-3.5 h-3.5"></i> Details
                                </button>
                                <dialog id="modal_webhook_{{ $webhook->id }}" class="modal text-left">
                                    <div class="modal-box max-w-2xl bg-base-100 rounded-3xl p-6">
                                        <h3 class="font-black text-lg text-neutral mb-2">Payload Webhook #{{ $webhook->id }}</h3>
                                        <p class="text-xs text-gray-400 font-mono mb-4">Key: {{ $webhook->idempotency_key }}</p>
                                        
                                        @if($webhook->error_message)
                                            <div class="p-3 mb-4 rounded-xl bg-error/10 border border-error/30 text-error text-xs font-semibold">
                                                <strong>Error:</strong> {{ $webhook->error_message }}
                                            </div>
                                        @endif

                                        <div class="bg-neutral text-neutral-content p-4 rounded-2xl overflow-x-auto text-[11px] font-mono leading-relaxed max-h-96">
                                            <pre>{{ json_encode($webhook->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>

                                        <div class="modal-action mt-6">
                                            <form method="dialog">
                                                <button class="btn btn-sm btn-neutral rounded-xl">Tutup</button>
                                            </form>
                                        </div>
                                    </div>
                                </dialog>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">
                                <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-2 opacity-50"></i>
                                <div class="font-bold">Belum ada riwayat webhook</div>
                                <div class="text-[11px]">Webhook dari POS akan otomatis tercatat di sini.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($webhooks->hasPages())
            <div class="p-4 border-t border-base-300">
                {{ $webhooks->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function showPayloadModal(id) {
    document.getElementById('modal_webhook_' + id).showModal();
}
</script>
@endsection
