<?php

namespace Tests\Feature\AI;

use App\AI\Agents\ErpAssistantAgent;
use App\AI\Tools\Finance\RevenueSummaryTool;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AI\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ErpAssistantAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_responds_with_faked_response(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        ErpAssistantAgent::fake(['Hello from the ERP assistant!']);

        $agent = ErpAssistantAgent::make($user);
        $agent->forUser($user);
        $response = $agent->prompt('What is my name?');

        $this->assertNotEmpty($response->text);

        ErpAssistantAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'What is my name'));
    }

    public function test_agent_streams_faked_response(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        ErpAssistantAgent::fake(['Streamed response content']);

        $agent = ErpAssistantAgent::make($user);
        $agent->forUser($user);
        $stream = $agent->stream('Show me allocation status');

        $content = '';
        foreach ($stream as $event) {
            $data = $event->toArray();
            if (isset($data['delta'])) {
                $content .= $data['delta'];
            }
        }

        $this->assertNotEmpty($content);
    }

    public function test_role_based_tool_filtering_super_admin_gets_all_tools(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $agent = ErpAssistantAgent::make($user);
        $tools = $agent->tools();

        // Super admin should get all 26 tools (4 Customer + 5 Finance + 3 Allocation + 3 Inventory + 3 Fulfillment + 3 Procurement + 3 Commercial + 2 PIM)
        $this->assertCount(26, $tools);
    }

    public function test_role_based_tool_filtering_viewer_gets_overview_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $agent = ErpAssistantAgent::make($user);
        $tools = $agent->tools();

        // Viewer should only get Overview-level tools (fewer than 26)
        $this->assertLessThan(26, count($tools));
        $this->assertGreaterThan(0, count($tools));
    }

    public function test_role_based_tool_filtering_null_role_gets_no_tools(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);
        // Set role to null in memory (DB column has NOT NULL constraint with default)
        $user->role = null;

        $agent = ErpAssistantAgent::make($user);
        $tools = $agent->tools();

        $this->assertCount(0, $tools);
    }

    public function test_chat_endpoint_returns_sse_stream(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        ErpAssistantAgent::fake(['Test response']);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_chat_endpoint_rate_limits(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);

        ErpAssistantAgent::fake(['response']);

        // Pre-fill the cache counter to simulate exhausted hourly limit
        $hourKey = 'ai_rate:'.$user->id.':hour:'.now()->format('Y-m-d-H');
        Cache::put($hourKey, 60, 3600);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => 'This should be rate limited',
        ]);

        $this->assertEquals(429, $response->getStatusCode());
        $response->assertJsonPath('message', fn (string $val): bool => str_contains($val, 'Rate limit exceeded'));
    }

    public function test_rate_limit_service_super_admin_exempt(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        // Pre-fill cache counter well above limits
        $hourKey = 'ai_rate:'.$user->id.':hour:'.now()->format('Y-m-d-H');
        Cache::put($hourKey, 999, 3600);

        $rateLimitService = app(RateLimitService::class);
        $result = $rateLimitService->check($user);

        $this->assertTrue($result['allowed']);
    }

    public function test_chat_endpoint_validates_message(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_chat_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/admin/ai/chat', [
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_instructions_returns_system_prompt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $agent = ErpAssistantAgent::make($user);
        $instructions = $agent->instructions();

        $this->assertIsString($instructions);
        $this->assertNotEmpty($instructions);
    }

    public function test_viewer_cannot_access_finance_tools(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $agent = ErpAssistantAgent::make($viewer);
        $tools = $agent->tools();

        $toolClasses = array_map(fn ($tool): string => $tool::class, $tools);

        // Viewer (Overview level) should NOT have access to Standard-level finance tools
        $this->assertNotContains(RevenueSummaryTool::class, $toolClasses);
    }

    public function test_viewer_tool_count_is_less_than_admin(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $viewerAgent = ErpAssistantAgent::make($viewer);
        $adminAgent = ErpAssistantAgent::make($admin);

        $this->assertLessThan(count($adminAgent->tools()), count($viewerAgent->tools()));
    }

    public function test_audit_log_created_after_chat(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        ErpAssistantAgent::fake(['Test audit response']);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => 'Create audit log test',
        ]);

        $response->assertStatus(200);

        // Stream the response to trigger audit log creation
        $response->streamedContent();

        $this->assertDatabaseHas('ai_audit_logs', [
            'user_id' => $user->id,
            'message_text' => 'Create audit log test',
        ]);
    }

    public function test_chat_endpoint_rejects_oversized_message(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($user)->postJson('/admin/ai/chat', [
            'message' => str_repeat('a', 2001),
        ]);

        $response->assertStatus(422);
    }
}
