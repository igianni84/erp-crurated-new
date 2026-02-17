<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class AiAssistant extends Page
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'AI Assistant';

    protected string $view = 'filament.pages.ai-assistant';

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
