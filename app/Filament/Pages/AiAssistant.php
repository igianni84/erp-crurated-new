<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AiAssistant extends Page
{
    protected ?string $maxContentWidth = 'full';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'AI Assistant';

    protected static string $view = 'filament.pages.ai-assistant';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'maxContextMessages' => (int) config('ai-assistant.max_context_messages', 30),
        ];
    }
}
