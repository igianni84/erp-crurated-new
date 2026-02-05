<?php

namespace App\Filament\Resources\Finance\StorageBillingResource\Pages;

use App\Enums\Finance\StorageBillingStatus;
use App\Filament\Resources\Finance\StorageBillingResource;
use App\Models\Finance\StorageBillingPeriod;
use Carbon\Carbon;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStorageBilling extends ListRecords
{
    protected static string $resource = StorageBillingResource::class;

    /**
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            // Create action will be implemented when needed
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $currentPeriodStart = Carbon::now()->startOfMonth();
        $currentPeriodEnd = Carbon::now()->endOfMonth();

        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-archive-box')
                ->badge(fn (): int => StorageBillingPeriod::count()),

            'current_period' => Tab::make('Current Period')
                ->icon('heroicon-o-calendar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('period_start', '>=', $currentPeriodStart)
                    ->where('period_end', '<=', $currentPeriodEnd))
                ->badge(fn (): int => StorageBillingPeriod::query()
                    ->where('period_start', '>=', $currentPeriodStart)
                    ->where('period_end', '<=', $currentPeriodEnd)
                    ->count())
                ->badgeColor('info'),

            'past_periods' => Tab::make('Past Periods')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('period_end', '<', $currentPeriodStart))
                ->badge(fn (): int => StorageBillingPeriod::query()
                    ->where('period_end', '<', $currentPeriodStart)
                    ->count())
                ->badgeColor('gray'),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-document')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StorageBillingStatus::Pending))
                ->badge(fn (): int => StorageBillingPeriod::where('status', StorageBillingStatus::Pending)->count())
                ->badgeColor('warning'),

            'blocked' => Tab::make('Blocked')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', StorageBillingStatus::Blocked))
                ->badge(fn (): int => StorageBillingPeriod::where('status', StorageBillingStatus::Blocked)->count())
                ->badgeColor('danger'),
        ];
    }
}
