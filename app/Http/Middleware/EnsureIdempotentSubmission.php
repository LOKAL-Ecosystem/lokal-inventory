<?php

namespace App\Http\Middleware;

use App\Models\FormToken;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Kiriman form yang mengubah stok hanya diproses sekali per token (hidden input `_idempotency_token`).
 *
 * - Token baru: dicatat per outlet (unique), request diproses. Bila hasilnya sukses, tujuan redirect
 *   disimpan; bila gagal (validasi, galat, exception) token dilepas supaya bisa dicoba lagi.
 * - Token yang sudah terpakai: tidak diproses ulang, pengguna diarahkan ke hasil pertama.
 * - Header `X-Form-Fetch` (dari JS): redirect dikembalikan sebagai JSON {redirect}, agar halaman
 *   dan isian form tetap utuh bila jaringan gagal.
 */
class EnsureIdempotentSubmission
{
    public const FIELD = '_idempotency_token';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input(self::FIELD);

        if (! is_string($token) || ! Str::isUuid($token)) {
            return $this->finish($request, $next($request));
        }

        $token = strtolower($token);

        try {
            $record = FormToken::create([
                'token' => $token,
                'action' => (string) $request->route()?->getName(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate($request, $token);
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $record->delete();

            throw $e;
        }

        $failed = $response->getStatusCode() >= 400
            || ($request->hasSession() && ($request->session()->get('errors')?->any() || $request->session()->has('error')));

        if ($failed) {
            $record->delete();
        } elseif ($response instanceof RedirectResponse) {
            $record->update(['result_url' => $response->getTargetUrl()]);
        }

        return $this->finish($request, $response);
    }

    protected function duplicate(Request $request, string $token): Response
    {
        $url = FormToken::where('token', $token)->value('result_url');

        if ($url === null) {
            // Kiriman pertama masih berjalan: jangan proses ganda.
            return $this->finish($request, back()->with('error', 'Permintaan yang sama sedang diproses. Tunggu sebentar, lalu muat ulang halaman.'));
        }

        return $this->finish($request, redirect()->to($url)->with('success', 'Data ini sudah tersimpan sebelumnya.'));
    }

    protected function finish(Request $request, Response $response): Response
    {
        if ($request->hasHeader('X-Form-Fetch') && $response instanceof RedirectResponse) {
            return new JsonResponse(['redirect' => $response->getTargetUrl()]);
        }

        return $response;
    }
}
