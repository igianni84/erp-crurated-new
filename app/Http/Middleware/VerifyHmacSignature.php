<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify HMAC-SHA256 signatures on API requests.
 *
 * Requires two headers:
 * - X-Signature: HMAC-SHA256 hex digest of "$timestamp.$body"
 * - X-Timestamp: Unix timestamp (must be within 5 minutes)
 *
 * The shared secret is read from config('services.trading_platform.hmac_secret').
 */
class VerifyHmacSignature
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.trading_platform.hmac_secret');

        if (empty($secret)) {
            return $this->reject('HMAC secret not configured', 500);
        }

        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');

        if (empty($signature) || empty($timestamp)) {
            return $this->reject('Missing X-Signature or X-Timestamp header');
        }

        if (! is_numeric($timestamp)) {
            return $this->reject('Invalid timestamp format');
        }

        $timestampInt = (int) $timestamp;
        $now = time();

        if (abs($now - $timestampInt) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            return $this->reject('Request timestamp expired');
        }

        $payload = $timestamp.'.'.$request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return $this->reject('Invalid HMAC signature');
        }

        return $next($request);
    }

    private function reject(string $message, int $status = 401): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
