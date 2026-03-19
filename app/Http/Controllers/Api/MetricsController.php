<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Simple metrics endpoint for monitoring tools.
 *
 * Returns structured JSON with queue, database, and runtime metrics.
 * Publicly accessible (no auth), rate-limited.
 */
class MetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'queue' => $this->queueMetrics(),
            'database' => $this->databaseMetrics(),
            'storage' => $this->storageMetrics(),
            'runtime' => $this->runtimeMetrics(),
        ]);
    }

    /**
     * @return array{pending_jobs: int, failed_jobs_24h: int}
     */
    protected function queueMetrics(): array
    {
        try {
            return [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs_24h' => DB::table('failed_jobs')
                    ->where('failed_at', '>=', now()->subDay())
                    ->count(),
            ];
        } catch (\Throwable) {
            return [
                'pending_jobs' => 0,
                'failed_jobs_24h' => 0,
            ];
        }
    }

    /**
     * @return array{connections: int}
     */
    protected function databaseMetrics(): array
    {
        try {
            $driver = config('database.default');

            if ($driver === 'mysql') {
                /** @var array<int, \stdClass> $result */
                $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
                $connections = isset($result[0]) ? (int) $result[0]->Value : 0;
            } else {
                $connections = 1;
            }

            return ['connections' => $connections];
        } catch (\Throwable) {
            return ['connections' => 0];
        }
    }

    /**
     * @return array{usage_mb: float}
     */
    protected function storageMetrics(): array
    {
        $storagePath = storage_path('app');

        if (! is_dir($storagePath)) {
            return ['usage_mb' => 0.0];
        }

        $bytes = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $bytes += $file->getSize();
            }
        }

        return ['usage_mb' => round($bytes / 1_048_576, 2)];
    }

    /**
     * @return array{php_version: string, laravel_version: string, environment: string, uptime_seconds: int}
     */
    protected function runtimeMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'uptime_seconds' => defined('LARAVEL_START') ? (int) (microtime(true) - LARAVEL_START) : 0,
        ];
    }
}
