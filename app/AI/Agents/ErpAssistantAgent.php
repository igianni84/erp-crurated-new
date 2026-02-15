<?php

namespace App\AI\Agents;

use App\AI\Tools\Customer\TopCustomersByRevenueTool;
use App\Models\User;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

#[Provider('anthropic')]
#[Model('claude-sonnet-4-5-20250929')]
#[MaxSteps(10)]
#[Temperature(0.3)]
class ErpAssistantAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        protected User $user
    ) {}

    public function instructions(): \Stringable|string
    {
        return (string) file_get_contents(app_path('AI/Prompts/erp-system-prompt.md'));
    }

    /**
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    public function tools(): array
    {
        if ($this->user->role === null) {
            return [];
        }

        return array_filter($this->allTools(), function ($tool): bool {
            if (method_exists($tool, 'authorizeForUser')) {
                return $tool->authorizeForUser($this->user);
            }

            return true;
        });
    }

    /**
     * @return array<\Laravel\Ai\Contracts\Tool>
     */
    protected function allTools(): array
    {
        return [
            new TopCustomersByRevenueTool,
        ];
    }

    protected function maxConversationMessages(): int
    {
        return (int) config('ai-assistant.max_context_messages', 30);
    }
}
