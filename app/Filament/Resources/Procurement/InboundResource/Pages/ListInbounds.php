<?php

namespace App\Filament\Resources\Procurement\InboundResource\Pages;

use App\Enums\Procurement\InboundStatus;
use App\Enums\Procurement\OwnershipFlag;
use App\Filament\Resources\Procurement\InboundResource;
use App\Models\Procurement\Inbound;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInbounds extends ListRecords
{
    protected static string $resource = InboundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Record Inbound')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    InboundStatus::Recorded->value,
                    InboundStatus::Routed->value,
                ]))
                ->badge($this->getActiveCount())
                ->badgeColor('primary'),

            'recorded' => Tab::make('Recorded')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InboundStatus::Recorded->value))
                ->badge($this->getRecordedCount())
                ->badgeColor('gray'),

            'routed' => Tab::make('Routed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InboundStatus::Routed->value))
                ->badge($this->getRoutedCount())
                ->badgeColor('warning'),

            'pending_ownership' => Tab::make('Pending Ownership')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('ownership_flag', OwnershipFlag::Pending->value)
                    ->whereIn('status', [
                        InboundStatus::Recorded->value,
                        InboundStatus::Routed->value,
                    ]))
                ->badge($this->getPendingOwnershipCount())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle'),

            'unlinked' => Tab::make('Unlinked')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereNull('procurement_intent_id')
                    ->whereIn('status', [
                        InboundStatus::Recorded->value,
                        InboundStatus::Routed->value,
                    ]))
                ->badge($this->getUnlinkedCount())
                ->badgeColor('warning')
                ->icon('heroicon-o-link-slash'),

            'awaiting_handoff' => Tab::make('Awaiting Hand-off')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', InboundStatus::Completed->value)
                    ->where('handed_to_module_b', false))
                ->badge($this->getAwaitingHandoffCount())
                ->badgeColor('info'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InboundStatus::Completed->value))
                ->badge($this->getCompletedCount())
                ->badgeColor('success'),

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
        return Inbound::query()
            ->whereIn('status', [
                InboundStatus::Recorded->value,
                InboundStatus::Routed->value,
            ])
            ->count();
    }

    private function getRecordedCount(): int
    {
        return Inbound::query()
            ->where('status', InboundStatus::Recorded->value)
            ->count();
    }

    private function getRoutedCount(): int
    {
        return Inbound::query()
            ->where('status', InboundStatus::Routed->value)
            ->count();
    }

    private function getPendingOwnershipCount(): int
    {
        return Inbound::query()
            ->where('ownership_flag', OwnershipFlag::Pending->value)
            ->whereIn('status', [
                InboundStatus::Recorded->value,
                InboundStatus::Routed->value,
            ])
            ->count();
    }

    private function getUnlinkedCount(): int
    {
        return Inbound::query()
            ->whereNull('procurement_intent_id')
            ->whereIn('status', [
                InboundStatus::Recorded->value,
                InboundStatus::Routed->value,
            ])
            ->count();
    }

    private function getAwaitingHandoffCount(): int
    {
        return Inbound::query()
            ->where('status', InboundStatus::Completed->value)
            ->where('handed_to_module_b', false)
            ->count();
    }

    private function getCompletedCount(): int
    {
        return Inbound::query()
            ->where('status', InboundStatus::Completed->value)
            ->count();
    }

    private function getAllCount(): int
    {
        return Inbound::count();
    }
}
