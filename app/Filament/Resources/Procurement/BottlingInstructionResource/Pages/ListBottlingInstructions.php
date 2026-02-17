<?php

namespace App\Filament\Resources\Procurement\BottlingInstructionResource\Pages;

use App\Enums\Procurement\BottlingInstructionStatus;
use App\Enums\Procurement\BottlingPreferenceStatus;
use App\Filament\Resources\Procurement\BottlingInstructionResource;
use App\Models\Procurement\BottlingInstruction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBottlingInstructions extends ListRecords
{
    protected static string $resource = BottlingInstructionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Bottling Instruction')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    BottlingInstructionStatus::Draft->value,
                    BottlingInstructionStatus::Active->value,
                ]))
                ->badge($this->getActiveCount())
                ->badgeColor('primary'),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BottlingInstructionStatus::Draft->value))
                ->badge($this->getDraftCount())
                ->badgeColor('gray'),

            'running' => Tab::make('Running')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BottlingInstructionStatus::Active->value))
                ->badge($this->getRunningCount())
                ->badgeColor('success'),

            'urgent' => Tab::make('Deadline < 30 days')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('bottling_deadline', '<=', now()->addDays(30))
                    ->where('bottling_deadline', '>=', now())
                    ->whereIn('status', [
                        BottlingInstructionStatus::Draft->value,
                        BottlingInstructionStatus::Active->value,
                    ]))
                ->badge($this->getUrgentCount())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle'),

            'pending_prefs' => Tab::make('Pending Preferences')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('preference_status', BottlingPreferenceStatus::Pending->value))
                ->badge($this->getPendingPreferencesCount())
                ->badgeColor('warning')
                ->icon('heroicon-o-bell-alert'),

            'executed' => Tab::make('Executed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BottlingInstructionStatus::Executed->value))
                ->badge($this->getExecutedCount())
                ->badgeColor('info'),

            'all' => Tab::make('All')
                ->badge($this->getAllCount()),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return 'active';
    }

    private function getActiveCount(): int
    {
        return BottlingInstruction::query()
            ->whereIn('status', [
                BottlingInstructionStatus::Draft->value,
                BottlingInstructionStatus::Active->value,
            ])
            ->count();
    }

    private function getDraftCount(): int
    {
        return BottlingInstruction::query()
            ->where('status', BottlingInstructionStatus::Draft->value)
            ->count();
    }

    private function getRunningCount(): int
    {
        return BottlingInstruction::query()
            ->where('status', BottlingInstructionStatus::Active->value)
            ->count();
    }

    private function getUrgentCount(): int
    {
        return BottlingInstruction::query()
            ->where('bottling_deadline', '<=', now()->addDays(30))
            ->where('bottling_deadline', '>=', now())
            ->whereIn('status', [
                BottlingInstructionStatus::Draft->value,
                BottlingInstructionStatus::Active->value,
            ])
            ->count();
    }

    private function getPendingPreferencesCount(): int
    {
        return BottlingInstruction::query()
            ->where('preference_status', BottlingPreferenceStatus::Pending->value)
            ->count();
    }

    private function getExecutedCount(): int
    {
        return BottlingInstruction::query()
            ->where('status', BottlingInstructionStatus::Executed->value)
            ->count();
    }

    private function getAllCount(): int
    {
        return BottlingInstruction::count();
    }
}
