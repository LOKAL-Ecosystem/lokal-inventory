<?php

namespace App\Http\Middleware;

use App\Support\LinkSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permintaan penautan dari Lokal POS wajib bertanda tangan (X-Lokal-Timestamp + X-Lokal-Signature).
 * Bila penautan mati (LOKAL_LINK_SECRET kosong), endpoint-nya seolah tidak ada.
 */
class VerifyLinkSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(LinkSignature::enabled(), 404);

        if (! LinkSignature::verify($request)) {
            return response()->json(['message' => 'Tanda tangan tidak valid atau kedaluwarsa.'], 401);
        }

        return $next($request);
    }
}
