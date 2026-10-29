<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LinkSignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Masuk lewat Lokal POS (satu login, lihat SsoController). Form email/password hanya tersedia
 * saat APP_ENV=local atau saat penautan mati (LOKAL_LINK_SECRET kosong), untuk pengembangan & test.
 */
class LoginController extends Controller
{
    public static function passwordLoginAllowed(): bool
    {
        return app()->environment('local') || ! LinkSignature::enabled();
    }

    public function create(): View
    {
        $posUrl = config('lokal.link.pos_url');

        return view('auth.login', [
            'ssoUrl' => LinkSignature::enabled() && filled($posUrl) ? rtrim($posUrl, '/') . '/go/stock' : null,
            'passwordLogin' => self::passwordLoginAllowed(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::passwordLoginAllowed(), 404);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Hanya keluar dari Inventory; sesi Lokal POS tidak tersentuh.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
