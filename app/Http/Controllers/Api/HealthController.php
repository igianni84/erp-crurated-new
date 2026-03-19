<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

        // Queue check
        $checks['queue'] = $this->checkQueue();
        if ($checks['queue']['status'] === 'error') {
            $allHealthy = false;
        }

        // Redis check
        $checks['redis'] = $this->checkRedis();
        if ($checks['redis']['status'] === 'error') {
            $allHealthy = false;
        }

        // Meilisearch check
        $checks['meilisearch'] = $this->checkMeilisearch();
        if ($checks['meilisearch']['status'] === 'error') {
            // Meilisearch failure = warning only (search degraded, not full outage)
        }

        return response()->json([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'version' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
            ],
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

    /**
     * @return array{status: string, pending_jobs: int, failed_jobs_24h: int, error?: string}
     */
    protected function checkQueue(): array
    {
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs24h = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count();

            $status = 'ok';
            if ($failedJobs24h > 10) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'pending_jobs' => $pendingJobs,
                'failed_jobs_24h' => $failedJobs24h,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'pending_jobs' => 0,
                'failed_jobs_24h' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    protected function checkMeilisearch(): array
    {
        if (config('scout.driver') !== 'meilisearch') {
            return ['status' => 'skipped'];
        }

        $start = hrtime(true);

        try {
            /** @var \Meilisearch\Client $client */
            $client = app(\Meilisearch\Client::class);
            $client->health();
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latencyMs, 2),
            ];
        } catch (\Throwable $e) {
            $latencyMs = (hrtime(true) - $start) / 1_000_000;

            return [
                'status' => 'warning',
                'latency_ms' => round($latencyMs, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{status: string, latency_ms?: float, error?: string}
     */
    protected function checkRedis(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['status' => 'skipped'];
        }

        $start = hrtime(true);

        try {
            Redis::ping();
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
