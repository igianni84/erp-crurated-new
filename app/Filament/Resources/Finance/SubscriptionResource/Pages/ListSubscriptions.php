<?php

namespace App\Filament\Resources\Finance\SubscriptionResource\Pages;

use App\Enums\Finance\SubscriptionStatus;
use App\Filament\Resources\Finance\SubscriptionResource;
use App\Models\Finance\Subscription;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

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
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-arrow-path')
                ->badge(fn (): int => Subscription::count()),

            'active' => Tab::make('Active')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SubscriptionStatus::Active))
                ->badge(fn (): int => Subscription::where('status', SubscriptionStatus::Active)->count())
                ->badgeColor('success'),

            'suspended' => Tab::make('Suspended')
                ->icon('heroicon-o-pause-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SubscriptionStatus::Suspended))
                ->badge(fn (): int => Subscription::where('status', SubscriptionStatus::Suspended)->count())
                ->badgeColor('warning'),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', SubscriptionStatus::Cancelled))
                ->badge(fn (): int => Subscription::where('status', SubscriptionStatus::Cancelled)->count())
                ->badgeColor('danger'),

            'due_for_billing' => Tab::make('Due for Billing')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', SubscriptionStatus::Active)
                    ->where('next_billing_date', '<=', now()->startOfDay()))
                ->badge(fn (): int => Subscription::where('status', SubscriptionStatus::Active)
                    ->where('next_billing_date', '<=', now()->startOfDay())
                    ->count())
                ->badgeColor('info'),
        ];
    }
}
