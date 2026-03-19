<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Health check endpoint for monitoring and alerting.
 *
 * Returns system health status with per-check latency.
 * Publicly accessible (no auth), rate-limited.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Database check
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'ok') {
            $allHealthy = false;
        }

        // Cache check
        $checks['cache'] = $this->checkCache();
        if ($checks['cache']['status'] !== 'ok') {
            $allHealthy = false;
        }

        // Storage check
        $checks['storage'] = $this->checkStorage();
        if ($checks['storage']['status'] !== 'ok') {
            $allHealthy = false;
        }

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    /**
     * @return array{status: string, latency_ms: float, error?: string}
     */
    protected function checkDatabase(): array
    {
        $start = hrtime(true);

        try {
            DB::select('SELECT 1');
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'error',
                'latency_ms' => round($latencyMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms: float, error?: string}
     */
    protected function checkCache(): array
    {
        $start = hrtime(true);

        try {
            $testKey = 'health:check:'.bin2hex(random_bytes(4));
            Cache::put($testKey, true, 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            if ($value !== true) {
                throw new \RuntimeException('Cache read/write mismatch');
            }

            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'error',
                'latency_ms' => round($latencyMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms: float, error?: string}
     */
    protected function checkStorage(): array
    {
        $start = hrtime(true);

        try {
            $testFile = 'health-check-'.bin2hex(random_bytes(4)).'.tmp';
            Storage::put($testFile, 'ok');
            $content = Storage::get($testFile);
            Storage::delete($testFile);

            if ($content !== 'ok') {
                throw new \RuntimeException('Storage read/write mismatch');
            }

            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'error',
                'latency_ms' => round($latencyMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }
}
