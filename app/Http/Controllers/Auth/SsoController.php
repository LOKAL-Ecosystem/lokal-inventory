<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\OutletLinkService;
use App\Support\LinkSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * GET /sso?token=...: satu login dari Lokal POS (kontrak v2.3). Token berlaku 60 detik dan hanya
 * sekali pakai (nonce disimpan di cache sampai exp). Data staf di token memperbarui user cermin.
 */
class SsoController extends Controller
{
    public function __invoke(Request $request, OutletLinkService $links): RedirectResponse
    {
        abort_unless(LinkSignature::enabled(), 404);

        $payload = LinkSignature::readToken((string) $request->query('token', ''));

        if (! $payload || ! isset($payload['pos_outlet_id'], $payload['pos_user_id'], $payload['name'], $payload['email'])) {
            return $this->fail('Tautan masuk tidak valid atau sudah kedaluwarsa. Buka lagi dari Lokal POS.');
        }

        // Cache::add atomik: hanya permintaan pertama dengan nonce ini yang lolos.
        $ttl = max(1, (int) $payload['exp'] - time() + 1);
        if (! Cache::add('lokal-sso-nonce:' . $payload['nonce'], true, $ttl)) {
            return $this->fail('Tautan masuk sudah dipakai. Buka lagi dari Lokal POS.');
        }

        $outlet = Outlet::where('pos_outlet_id', (int) $payload['pos_outlet_id'])->first();

        if (! $outlet) {
            return $this->fail('Outlet ini belum tersambung ke Lokal Stock. Coba lagi beberapa saat lagi.');
        }

        $user = $links->syncStaff($outlet, $payload);

        if (! $user->is_active) {
            return $this->fail('Akun Anda dinonaktifkan di Lokal POS.');
        }

        if (Auth::check()) {
            Auth::logout();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    protected function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('error', $message);
    }
}
