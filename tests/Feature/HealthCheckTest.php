<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_200_with_correct_structure(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database' => ['status', 'latency_ms'],
                'cache' => ['status', 'latency_ms'],
                'storage' => ['status', 'latency_ms'],
                'queue' => ['status', 'pending_jobs', 'failed_jobs_24h'],
                'redis' => ['status'],
            ],
            'version' => ['php', 'laravel', 'environment'],
        ]);
        $response->assertJson(['status' => 'healthy']);
    }

    public function test_health_checks_database_connectivity(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('checks.database.status', 'ok');
    }

    public function test_health_checks_cache_connectivity(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('checks.cache.status', 'ok');
    }

    public function test_health_checks_storage_connectivity(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('checks.storage.status', 'ok');
    }

    public function test_health_is_publicly_accessible(): void
    {
        // No authentication required
        $response = $this->getJson('/api/health');

        $response->assertOk();
    }

    public function test_health_returns_latency_as_number(): void
    {
        $response = $this->getJson('/api/health');

        $data = $response->json();

        $this->assertIsFloat($data['checks']['database']['latency_ms']);
        $this->assertIsFloat($data['checks']['cache']['latency_ms']);
        $this->assertIsFloat($data['checks']['storage']['latency_ms']);
    }

    public function test_laravel_health_endpoint_returns_200(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    public function test_health_checks_queue_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $data = $response->json('checks.queue');

        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('pending_jobs', $data);
        $this->assertArrayHasKey('failed_jobs_24h', $data);
        $this->assertIsInt($data['pending_jobs']);
        $this->assertIsInt($data['failed_jobs_24h']);
    }

    public function test_health_checks_redis_when_not_configured(): void
    {
        // In testing, cache/queue default to array/sync, so Redis is skipped
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('checks.redis.status', 'skipped');
    }

    public function test_health_returns_version_info(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $data = $response->json('version');

        $this->assertSame(PHP_VERSION, $data['php']);
        $this->assertSame(app()->version(), $data['laravel']);
        $this->assertSame('testing', $data['environment']);
    }

    public function test_metrics_endpoint_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'timestamp',
            'queue' => ['pending_jobs', 'failed_jobs_24h'],
            'database' => ['connections'],
            'storage' => ['usage_mb'],
            'runtime' => ['php_version', 'laravel_version', 'environment', 'uptime_seconds'],
        ]);
    }

    public function test_metrics_is_publicly_accessible(): void
    {
        $response = $this->getJson('/api/metrics');

        $response->assertOk();
    }

    public function test_metrics_returns_valid_runtime_data(): void
    {
        $response = $this->getJson('/api/metrics');

        $data = $response->json('runtime');

        $this->assertSame(PHP_VERSION, $data['php_version']);
        $this->assertSame(app()->version(), $data['laravel_version']);
        $this->assertSame('testing', $data['environment']);
        $this->assertIsInt($data['uptime_seconds']);
    }

    public function test_health_hides_version_details_in_production(): void
    {
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('version.status', 'current');
        $response->assertJsonMissingPath('version.php');
        $response->assertJsonMissingPath('version.laravel');
        $response->assertJsonMissingPath('version.environment');
    }

    public function test_metrics_hides_version_details_in_production(): void
    {
        $this->app['env'] = 'production';

        $response = $this->getJson('/api/metrics');

        $response->assertOk();
        $response->assertJsonMissingPath('runtime.php_version');
        $response->assertJsonMissingPath('runtime.laravel_version');
        $response->assertJsonMissingPath('runtime.environment');
        $this->assertArrayHasKey('uptime_seconds', $response->json('runtime'));
    }
}
