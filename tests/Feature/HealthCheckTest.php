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
            ],
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
}
