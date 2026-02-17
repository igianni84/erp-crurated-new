<?php

namespace App\Filament\Resources\Procurement\ProcurementIntentResource\Pages;

use App\Enums\Procurement\ProcurementIntentStatus;
use App\Filament\Resources\Procurement\ProcurementIntentResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProcurementIntents extends ListRecords
{
    protected static string $resource = ProcurementIntentResource::class;

    /**
     * Get the header actions for the list page.
     *
     * @return array<Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('aggregated_view')
                ->label('View Aggregated by Product')
                ->icon('heroicon-o-chart-bar-square')
                ->color('info')
                ->url(fn (): string => static::getResource()::getUrl('aggregated')),
        ];
    }

    /**
     * Get tabs for filtering by status.
     *
     * @return array<string, \Filament\Schemas\Components\Tabs\Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    ProcurementIntentStatus::Draft,
                    ProcurementIntentStatus::Approved,
                    ProcurementIntentStatus::Executed,
                ]))
                ->badge(fn () => $this->getActiveIntentsCount())
                ->badgeColor('primary'),

            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProcurementIntentStatus::Draft))
                ->badge(fn () => $this->getDraftIntentsCount())
                ->badgeColor('warning'),

            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProcurementIntentStatus::Approved))
                ->badge(fn () => $this->getApprovedIntentsCount())
                ->badgeColor('success'),

            'executed' => Tab::make('Executed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProcurementIntentStatus::Executed))
                ->badge(fn () => $this->getExecutedIntentsCount())
                ->badgeColor('info'),

            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ProcurementIntentStatus::Closed))
                ->badge(fn () => $this->getClosedIntentsCount())
                ->badgeColor('gray'),

            'all' => Tab::make('All'),
        ];
    }

    /**
     * Get the default active tab.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    /**
     * Get count of active intents (non-closed).
     */
    private function getActiveIntentsCount(): int
    {
        return static::getResource()::getEloquentQuery()
            ->whereIn('status', [
                ProcurementIntentStatus::Draft,
                ProcurementIntentStatus::Approved,
                ProcurementIntentStatus::Executed,
            ])
            ->count();
    }

    /**
     * Get count of draft intents.
     */
    private function getDraftIntentsCount(): int
    {
        return static::getResource()::getEloquentQuery()
            ->where('status', ProcurementIntentStatus::Draft)
            ->count();
    }

    /**
     * Get count of approved intents.
     */
    private function getApprovedIntentsCount(): int
    {
        return static::getResource()::getEloquentQuery()
            ->where('status', ProcurementIntentStatus::Approved)
            ->count();
    }

    /**
     * Get count of executed intents.
     */
    private function getExecutedIntentsCount(): int
    {
        return static::getResource()::getEloquentQuery()
            ->where('status', ProcurementIntentStatus::Executed)
            ->count();
    }

    /**
     * Get count of closed intents.
     */
    private function getClosedIntentsCount(): int
    {
        return static::getResource()::getEloquentQuery()
            ->where('status', ProcurementIntentStatus::Closed)
            ->count();
    }
}
