<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.pos_webhook.secret') 
            ?? env('POS_WEBHOOK_SECRET') 
            ?? env('WEBHOOK_SECRET');

        if (empty($secret)) {
            Log::error('POS Webhook verification failed: POS_WEBHOOK_SECRET is not configured in .env file.');
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret configuration error.',
            ], 500);
        }

        $signatureHeader = $request->header('X-Signature') ?? $request->header('X-Webhook-Signature');

        if (!$signatureHeader) {
            Log::warning('Spoofing attempt or unauthenticated webhook request: Missing signature header.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Missing signature header.',
            ], 401);
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($computedSignature, $signatureHeader)) {
            Log::warning('Spoofing attempt or invalid signature on webhook endpoint.', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'received_signature' => $signatureHeader,
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
