<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessConfirmedOrder;
use App\Jobs\ProcessVoidedOrder;
use App\Jobs\SyncCatalogFromPos;
use App\Models\Outlet;
use App\Models\WebhookInbox;
use App\Services\CatalogSyncService;
use App\Services\LokalPosClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Pengaturan koneksi ke Lokal POS: URL, API key, webhook secret (disimpan terenkripsi,
 * hanya ditampilkan 4 karakter terakhir), tes koneksi, dan sinkron katalog manual.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $outlet = $request->user()->outlet;

        return view('settings.edit', [
            'outlet' => $outlet,
            'maskedKey' => Outlet::mask($outlet->pos_api_key),
            'maskedSecret' => Outlet::mask($outlet->webhook_secret),
            'webhookUrl' => $outlet->webhookUrl(),
            'scopes' => LokalPosClient::SCOPES,
            'inbox' => WebhookInbox::latest('received_at')->latest('id')->limit(25)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $outlet = $request->user()->outlet;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'pos_outlet_id' => ['nullable', 'integer', 'min:1', 'unique:outlets,pos_outlet_id,' . $outlet->id],
            'pos_base_url' => ['nullable', 'url', 'max:255'],
            'pos_api_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        // Kolom rahasia yang dikosongkan berarti "tidak diubah".
        foreach (['pos_api_key', 'webhook_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        $outlet->update($data);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    /**
     * Buat ulang token URL webhook (mis. URL bocor). URL lama berhenti berlaku; ganti di POS.
     */
    public function regenerateWebhookUrl(Request $request): RedirectResponse
    {
        $request->user()->outlet->forceFill(['webhook_token' => \Illuminate\Support\Str::random(40)])->save();

        return back()->with('success', 'URL webhook dibuat ulang. Perbarui URL di Integrasi → Webhook keluar di Lokal POS.');
    }

    public function test(Request $request): RedirectResponse
    {
        $client = LokalPosClient::for($request->user()->outlet);

        try {
            $results = $client->probeScopes();
        } catch (Throwable $e) {
            return back()->with('error', 'Koneksi gagal: ' . $e->getMessage());
        }

        $missing = collect($results)->reject(fn ($r) => $r['ok'])->keys();
        $flash = ['scope_results' => $results];

        if ($missing->isEmpty()) {
            return back()->with($flash)->with('success', 'Terhubung ke Lokal POS. Semua scope token tersedia.');
        }

        return back()->with($flash)->with('error', 'Ada scope yang belum berfungsi: ' . $missing->implode(', ') . '.');
    }

    public function sync(Request $request, CatalogSyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->sync($request->user()->outlet);
        } catch (Throwable $e) {
            return back()->with('error', 'Sinkron gagal: ' . $e->getMessage());
        }

        return back()->with('success', "Katalog disinkronkan: {$result['products']} produk, {$result['modifiers']} opsi.");
    }

    /**
     * Proses ulang event inbox yang gagal / belum selesai.
     */
    public function retry(Request $request, WebhookInbox $inbox): RedirectResponse
    {
        if ($inbox->processed_at) {
            return back()->with('error', 'Event ini sudah diproses.');
        }

        $outletId = $inbox->outlet_id;

        try {
            match ($inbox->event_type) {
                'order.confirmed' => ProcessConfirmedOrder::dispatchSync($outletId, $inbox->id),
                'order.voided' => ProcessVoidedOrder::dispatchSync($outletId, $inbox->id),
                'catalog.updated' => SyncCatalogFromPos::dispatchSync($outletId, $inbox->id),
                default => $inbox->forceFill(['processed_at' => now()])->save(),
            };
        } catch (Throwable $e) {
            return back()->with('error', 'Masih gagal: ' . $e->getMessage());
        }

        return back()->with('success', 'Event diproses.');
    }
}
