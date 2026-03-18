<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_docs_page_loads_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/docs/api')
            ->assertOk();
    }

    public function test_api_docs_spec_returns_valid_json(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)
            ->get('/docs/api.json');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $json = $response->json();
        $this->assertArrayHasKey('openapi', $json);
        $this->assertArrayHasKey('info', $json);
        $this->assertEquals('1.0.0', $json['info']['version']);
    }
}
