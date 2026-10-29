<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessConfirmedOrder;
use App\Jobs\ProcessVoidedOrder;
use App\Jobs\SyncCatalogFromPos;
use App\Models\WebhookInbox;
use App\Services\CatalogSyncService;
use App\Services\LokalPosClient;
use App\Support\LinkSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Koneksi ke Lokal POS: hanya status. URL, API key, dan webhook secret diisi otomatis oleh
 * penautan dari POS (kontrak v2.3) dan tidak pernah ditampilkan. Tersisa: tes koneksi,
 * sinkron katalog manual, dan proses ulang event webhook yang gagal.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $outlet = $request->user()->outlet;

        return view('settings.edit', [
            'outlet' => $outlet,
            'linkEnabled' => LinkSignature::enabled(),
            'scopes' => LokalPosClient::SCOPES,
            'failedEvents' => WebhookInbox::whereNull('processed_at')->whereNotNull('last_error')->count(),
            'inbox' => WebhookInbox::latest('received_at')->latest('id')->limit(25)->get(),
        ]);
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
