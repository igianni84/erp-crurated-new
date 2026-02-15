<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAuditViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_assistant_page_accessible_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->get(AiAssistant::getUrl())
            ->assertSuccessful();
    }

    public function test_ai_assistant_page_requires_authentication(): void
    {
        $this->get('/admin/ai-assistant')
            ->assertRedirect();
    }

    public function test_ai_assistant_renders_livewire_component(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($user)
            ->test(AiAssistant::class)
            ->assertSuccessful();
    }

    public function test_ai_audit_viewer_accessible_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->get(AiAuditViewer::getUrl())
            ->assertSuccessful();
    }

    public function test_ai_audit_viewer_accessible_for_super_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($user)
            ->get(AiAuditViewer::getUrl())
            ->assertSuccessful();
    }

    public function test_ai_audit_viewer_forbidden_for_viewer(): void
    {
        $user = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($user)
            ->get(AiAuditViewer::getUrl())
            ->assertForbidden();
    }

    public function test_ai_audit_viewer_forbidden_for_editor(): void
    {
        $user = User::factory()->create(['role' => UserRole::Editor]);

        $this->actingAs($user)
            ->get(AiAuditViewer::getUrl())
            ->assertForbidden();
    }

    public function test_conversation_api_index_requires_auth(): void
    {
        $this->getJson('/admin/ai/conversations')
            ->assertStatus(401);
    }

    public function test_conversation_api_index_returns_empty_for_new_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->getJson('/admin/ai/conversations')
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    }

    public function test_conversation_api_messages_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->getJson('/admin/ai/conversations/nonexistent-id/messages')
            ->assertStatus(404);
    }

    public function test_conversation_api_delete_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)
            ->deleteJson('/admin/ai/conversations/nonexistent-id')
            ->assertStatus(404);
    }
}
